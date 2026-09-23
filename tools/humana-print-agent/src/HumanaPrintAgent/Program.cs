using System.Text.Json;
using HumanaPrintAgent;
using HumanaPrintAgent.Models;
using HumanaPrintAgent.Printing;
using HumanaPrintAgent.Security;
using Microsoft.Extensions.Options;

var builder = WebApplication.CreateBuilder(args);

builder.Services.Configure<PrintOptions>(builder.Configuration.GetSection(PrintOptions.SectionName));

// Resolve listen URL before building the host so Local stays loopback and Lan stays explicit.
var earlyPrint = builder.Configuration.GetSection(PrintOptions.SectionName).Get<PrintOptions>() ?? new PrintOptions();
var listenUrl = ListenEndpoint.ResolveUrl(earlyPrint);
builder.WebHost.UseUrls(listenUrl);

builder.Services.AddSingleton<JobIdStore>(sp =>
{
    var options = sp.GetRequiredService<IOptions<PrintOptions>>().Value;
    var path = string.IsNullOrWhiteSpace(options.JobStorePath)
        ? Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
            "HumanaPrintAgent",
            "jobs.json")
        : options.JobStorePath;
    return new JobIdStore(path);
});

builder.Services.AddSingleton<RequestRateLimiter>(sp =>
{
    var options = sp.GetRequiredService<IOptions<PrintOptions>>().Value;
    return new RequestRateLimiter(options.RateLimitPerMinute);
});

builder.Services.AddSingleton<IPrintProvider>(sp =>
{
    var options = sp.GetRequiredService<IOptions<PrintOptions>>().Value;
    var env = sp.GetRequiredService<IHostEnvironment>();
    var mode = options.Mode.Trim();

    if (string.Equals(mode, "Spooler", StringComparison.OrdinalIgnoreCase))
    {
        if (!OperatingSystem.IsWindows())
        {
            throw new InvalidOperationException("Spooler mode requires Windows.");
        }

        return new WindowsSpoolerProvider();
    }

    if (string.Equals(mode, "Mock", StringComparison.OrdinalIgnoreCase))
    {
        if (env.IsProduction())
        {
            throw new InvalidOperationException("MockPrintProvider is blocked in Production. Set Print:Mode=Spooler.");
        }

        var dir = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData),
            "HumanaPrintAgent",
            "mock-jobs");
        return new MockPrintProvider(dir);
    }

    throw new InvalidOperationException($"Unknown Print:Mode '{mode}'. Use Mock or Spooler.");
});

var app = builder.Build();
var printOptions = app.Services.GetRequiredService<IOptions<PrintOptions>>().Value;
var provider = app.Services.GetRequiredService<IPrintProvider>();
var jobs = app.Services.GetRequiredService<JobIdStore>();
var rateLimiter = app.Services.GetRequiredService<RequestRateLimiter>();
var env = app.Services.GetRequiredService<IHostEnvironment>();
var maxBytes = printOptions.MaxRequestBytes > 0 ? printOptions.MaxRequestBytes : 65536;
var isLan = string.Equals(printOptions.ListenMode, ListenEndpoint.LanMode, StringComparison.OrdinalIgnoreCase);

if (string.IsNullOrWhiteSpace(printOptions.PairingSecret) || printOptions.PairingSecret.Length < 32)
{
    throw new InvalidOperationException("Print:PairingSecret must be configured with at least 32 characters.");
}

if (string.Equals(printOptions.Mode, "Mock", StringComparison.OrdinalIgnoreCase) && env.IsProduction())
{
    throw new InvalidOperationException("Mock mode cannot run in Production.");
}

if (isLan && printOptions.AllowedOrigins is not { Length: > 0 })
{
    app.Logger.LogWarning(
        "ListenMode=Lan without Print:AllowedOrigins. CORS will not reflect arbitrary origins; HMAC grants remain mandatory.");
}

app.Use(async (context, next) =>
{
    var origin = context.Request.Headers.Origin.ToString();
    if (!string.IsNullOrWhiteSpace(origin) && IsAllowedOrigin(origin, printOptions.AllowedOrigins, isLan))
    {
        context.Response.Headers.Append("Access-Control-Allow-Origin", origin);
        context.Response.Headers.Append("Vary", "Origin");
        context.Response.Headers.Append("Access-Control-Allow-Headers", "Content-Type, Accept");
        context.Response.Headers.Append("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
    }

    if (HttpMethods.IsOptions(context.Request.Method))
    {
        context.Response.StatusCode = StatusCodes.Status204NoContent;
        return;
    }

    if (context.Request.ContentLength is long length && length > maxBytes)
    {
        context.Response.StatusCode = StatusCodes.Status413PayloadTooLarge;
        return;
    }

    await next();
});

app.MapGet("/health", () => Results.Json(new
{
    status = "ok",
    version = typeof(Program).Assembly.GetName().Version?.ToString() ?? "1.0.0",
    provider = provider.ModeName,
    mode = provider.ModeName, // backward-compatible alias
    listenMode = string.Equals(printOptions.ListenMode, ListenEndpoint.LanMode, StringComparison.OrdinalIgnoreCase)
        ? ListenEndpoint.LanMode
        : ListenEndpoint.LocalMode,
    bind = listenUrl,
}));

app.MapPost("/printers", async (HttpRequest request) =>
{
    if (!rateLimiter.IsAllowed(ClientKey(request)))
    {
        return Results.Json(new { status = "error", detail = "Rate limit exceeded." }, statusCode: StatusCodes.Status429TooManyRequests);
    }

    using var doc = await ReadJsonAsync(request, maxBytes);
    if (doc is null)
    {
        return Results.Json(new { status = "error", detail = "Request body too large or invalid." }, statusCode: StatusCodes.Status413PayloadTooLarge);
    }

    if (!TryGetSignedGrant(doc.RootElement, out var grantEl, out var signature))
    {
        return Results.BadRequest(new { status = "error", detail = "grant and signature are required." });
    }

    if (!GrantSigner.Verify(grantEl, signature, printOptions.PairingSecret))
    {
        return Results.Unauthorized();
    }

    if (!TryReadGrant(grantEl, out var grant) || grant.Action != "list_printers")
    {
        return Results.BadRequest(new { status = "error", detail = "Invalid grant action." });
    }

    if (grant.Exp < DateTimeOffset.UtcNow.ToUnixTimeSeconds())
    {
        return Results.Json(new { status = "error", detail = "Grant expired." }, statusCode: StatusCodes.Status403Forbidden);
    }

    return Results.Json(provider.ListPrinters());
});

app.MapPost("/print/ticket", async (HttpRequest request) =>
    await ExecutePrintAsync(request, expectedAction: "print_ticket"));

app.MapPost("/print/test", async (HttpRequest request) =>
    await ExecutePrintAsync(request, expectedAction: "print_test"));

app.Logger.LogInformation("Humana Print Agent listening on {Url} (ListenMode={ListenMode}, Provider={Provider})", listenUrl, printOptions.ListenMode, provider.ModeName);

app.Run();

async Task<IResult> ExecutePrintAsync(HttpRequest request, string expectedAction)
{
    if (!rateLimiter.IsAllowed(ClientKey(request)))
    {
        return Results.Json(new PrintResult { Status = "error", Printed = false, Detail = "Rate limit exceeded." }, statusCode: 429);
    }

    using var doc = await ReadJsonAsync(request, maxBytes);
    if (doc is null)
    {
        return Results.Json(new PrintResult { Status = "error", Printed = false, Detail = "Request body too large or invalid." }, statusCode: 413);
    }

    if (!TryGetSignedGrant(doc.RootElement, out var grantEl, out var signature))
    {
        return Results.BadRequest(new PrintResult { Status = "error", Printed = false, Detail = "grant and signature are required." });
    }

    if (!GrantSigner.Verify(grantEl, signature, printOptions.PairingSecret))
    {
        return Results.Unauthorized();
    }

    if (!TryReadGrant(grantEl, out var grant))
    {
        return Results.BadRequest(new PrintResult { Status = "error", Printed = false, Detail = "Invalid grant." });
    }

    if (!string.Equals(grant.Action, expectedAction, StringComparison.Ordinal))
    {
        return Results.BadRequest(new PrintResult { Status = "error", Printed = false, Detail = "Grant action mismatch." });
    }

    if (grant.Exp < DateTimeOffset.UtcNow.ToUnixTimeSeconds())
    {
        return Results.Json(new PrintResult { Status = "error", Printed = false, Detail = "Grant expired." }, statusCode: 403);
    }

    if (string.IsNullOrWhiteSpace(grant.JobId) || string.IsNullOrWhiteSpace(grant.Printer) || grant.Ticket is null)
    {
        return Results.BadRequest(new PrintResult { Status = "error", Printed = false, Detail = "Incomplete grant." });
    }

    if (!jobs.TryBegin(grant.JobId))
    {
        return Results.Json(new PrintResult
        {
            Status = "duplicate",
            Printed = false,
            JobId = grant.JobId,
            Detail = "Job already processed.",
        });
    }

    var width = grant.PaperWidth is "58" or "80" ? grant.PaperWidth : "80";
    var bytes = EscPosFormatter.FormatTicket(grant.Ticket, width!, grant.AutoCut, isTest: grant.Action == "print_test");
    var result = await provider.PrintAsync(grant.Printer!, bytes, grant.JobId, request.HttpContext.RequestAborted);
    jobs.MarkSeen(grant.JobId);
    return Results.Json(result);
}

static async Task<JsonDocument?> ReadJsonAsync(HttpRequest request, int maxBytes)
{
    try
    {
        await using var limited = new LimitedReadStream(request.Body, maxBytes);
        return await JsonDocument.ParseAsync(limited);
    }
    catch (InvalidOperationException)
    {
        return null;
    }
    catch (JsonException)
    {
        return null;
    }
}

static string ClientKey(HttpRequest request) =>
    request.HttpContext.Connection.RemoteIpAddress?.ToString() ?? "unknown";

static bool TryGetSignedGrant(JsonElement root, out JsonElement grantEl, out string signature)
{
    grantEl = default;
    signature = string.Empty;
    if (!root.TryGetProperty("grant", out grantEl) || !root.TryGetProperty("signature", out var sigEl))
    {
        return false;
    }

    signature = sigEl.GetString() ?? string.Empty;
    return true;
}

static bool TryReadGrant(JsonElement grantEl, out PrintGrant grant)
{
    try
    {
        grant = JsonSerializer.Deserialize<PrintGrant>(grantEl.GetRawText(), new JsonSerializerOptions
        {
            PropertyNameCaseInsensitive = true,
        }) ?? new PrintGrant();
        return !string.IsNullOrWhiteSpace(grant.Action) && !string.IsNullOrWhiteSpace(grant.JobId);
    }
    catch
    {
        grant = new PrintGrant();
        return false;
    }
}

static bool IsAllowedOrigin(string origin, string[]? allowed, bool lanMode)
{
    if (allowed is { Length: > 0 })
    {
        return allowed.Contains(origin, StringComparer.OrdinalIgnoreCase);
    }

    // Local defaults: only loopback origins for CORS reflection.
    if (!lanMode)
    {
        return origin.StartsWith("http://localhost", StringComparison.OrdinalIgnoreCase)
            || origin.StartsWith("https://localhost", StringComparison.OrdinalIgnoreCase)
            || origin.StartsWith("http://127.0.0.1", StringComparison.OrdinalIgnoreCase)
            || origin.StartsWith("https://127.0.0.1", StringComparison.OrdinalIgnoreCase);
    }

    // Lan without allowlist: do not reflect Origin (CORS is not auth; HMAC still required).
    return false;
}

public partial class Program;

/// <summary>Rejects bodies larger than maxBytes while streaming.</summary>
file sealed class LimitedReadStream : Stream
{
    private readonly Stream _inner;
    private readonly long _max;
    private long _read;

    public LimitedReadStream(Stream inner, long max)
    {
        _inner = inner;
        _max = max;
    }

    public override bool CanRead => true;
    public override bool CanSeek => false;
    public override bool CanWrite => false;
    public override long Length => throw new NotSupportedException();
    public override long Position { get => throw new NotSupportedException(); set => throw new NotSupportedException(); }
    public override void Flush() => _inner.Flush();
    public override long Seek(long offset, SeekOrigin origin) => throw new NotSupportedException();
    public override void SetLength(long value) => throw new NotSupportedException();
    public override void Write(byte[] buffer, int offset, int count) => throw new NotSupportedException();

    public override int Read(byte[] buffer, int offset, int count)
    {
        var n = _inner.Read(buffer, offset, count);
        _read += n;
        if (_read > _max)
        {
            throw new InvalidOperationException("Body too large.");
        }

        return n;
    }

    public override async ValueTask<int> ReadAsync(Memory<byte> buffer, CancellationToken cancellationToken = default)
    {
        var n = await _inner.ReadAsync(buffer, cancellationToken);
        _read += n;
        if (_read > _max)
        {
            throw new InvalidOperationException("Body too large.");
        }

        return n;
    }
}
