namespace HumanaPrintAgent;

/// <summary>
/// Resolves the HTTP listen URL from ListenMode + Port + BindHost.
/// Local always binds loopback. Lan never activates unless BindHost is set explicitly.
/// </summary>
public static class ListenEndpoint
{
    public const string LocalMode = "Local";
    public const string LanMode = "Lan";

    public static string ResolveUrl(PrintOptions options)
    {
        var port = options.Port is >= 1024 and <= 65535 ? options.Port : 17321;
        var listen = (options.ListenMode ?? LocalMode).Trim();

        if (string.Equals(listen, LocalMode, StringComparison.OrdinalIgnoreCase))
        {
            return $"http://127.0.0.1:{port}";
        }

        if (string.Equals(listen, LanMode, StringComparison.OrdinalIgnoreCase))
        {
            var host = (options.BindHost ?? string.Empty).Trim();
            if (string.IsNullOrWhiteSpace(host))
            {
                throw new InvalidOperationException(
                    "Print:ListenMode=Lan requires Print:BindHost to be set explicitly (e.g. 192.168.1.50 or 0.0.0.0). LAN is never enabled by default.");
            }

            if (host.Contains("://", StringComparison.Ordinal) || host.Contains('/'))
            {
                throw new InvalidOperationException("Print:BindHost must be a host or IP, without scheme or path.");
            }

            return $"http://{host}:{port}";
        }

        throw new InvalidOperationException($"Unknown Print:ListenMode '{listen}'. Use Local or Lan.");
    }

    public static bool IsLoopbackUrl(string url) =>
        url.StartsWith("http://127.0.0.1:", StringComparison.OrdinalIgnoreCase)
        || url.StartsWith("http://localhost:", StringComparison.OrdinalIgnoreCase)
        || url.StartsWith("http://[::1]:", StringComparison.OrdinalIgnoreCase);
}
