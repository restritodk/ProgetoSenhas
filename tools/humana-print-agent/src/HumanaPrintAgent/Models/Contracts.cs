namespace HumanaPrintAgent.Models;

public sealed class TicketPayload
{
    public string DisplayCode { get; set; } = string.Empty;
    public string TypeLabel { get; set; } = string.Empty;
    public string ClinicName { get; set; } = string.Empty;
    public string UnitName { get; set; } = string.Empty;
    public string IssuedAtLabel { get; set; } = string.Empty;
    public string Message { get; set; } = string.Empty;
}

public sealed class PrintGrant
{
    public int V { get; set; }
    public string Action { get; set; } = string.Empty;
    public string JobId { get; set; } = string.Empty;
    public string? Printer { get; set; }
    public string? PaperWidth { get; set; }
    public bool AutoCut { get; set; }
    public TicketPayload? Ticket { get; set; }
    public long Exp { get; set; }
    public string Nonce { get; set; } = string.Empty;
}

public sealed class SignedRequest
{
    public PrintGrant Grant { get; set; } = new();
    public string Signature { get; set; } = string.Empty;
}

public sealed class PrinterInfo
{
    public string Name { get; set; } = string.Empty;
    public bool IsDefault { get; set; }
    public string Status { get; set; } = "available";
}

public sealed class PrintResult
{
    public string Status { get; set; } = "ok";
    public bool Printed { get; set; }
    public string? JobId { get; set; }
    public string? Detail { get; set; }
}
