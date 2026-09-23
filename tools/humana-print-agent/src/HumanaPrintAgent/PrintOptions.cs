namespace HumanaPrintAgent;

public sealed class PrintOptions
{
    public const string SectionName = "Print";

    /// <summary>Mock | Spooler — print provider (not network listen mode).</summary>
    public string Mode { get; set; } = "Mock";

    /// <summary>Local | Lan — network listen mode. Default Local. Lan is never automatic.</summary>
    public string ListenMode { get; set; } = ListenEndpoint.LocalMode;

    /// <summary>TCP port (default 17321).</summary>
    public int Port { get; set; } = 17321;

    /// <summary>
    /// Bind host for Lan mode only (e.g. 192.168.1.50 or 0.0.0.0).
    /// Ignored in Local mode (always 127.0.0.1). Required when ListenMode=Lan.
    /// </summary>
    public string BindHost { get; set; } = string.Empty;

    /// <summary>Shared pairing secret with Laravel (never sent to the browser).</summary>
    public string PairingSecret { get; set; } = string.Empty;

    public string JobStorePath { get; set; } = string.Empty;

    /// <summary>CORS allowlist. Optional in Local; strongly recommended in Lan.</summary>
    public string[] AllowedOrigins { get; set; } = [];

    /// <summary>Maximum JSON body size in bytes.</summary>
    public int MaxRequestBytes { get; set; } = 65536;

    /// <summary>Max authenticated requests per client IP per minute.</summary>
    public int RateLimitPerMinute { get; set; } = 120;
}
