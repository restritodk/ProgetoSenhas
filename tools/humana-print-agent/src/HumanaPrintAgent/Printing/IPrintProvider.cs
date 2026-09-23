using HumanaPrintAgent.Models;

namespace HumanaPrintAgent.Printing;

public interface IPrintProvider
{
    string ModeName { get; }

    IReadOnlyList<PrinterInfo> ListPrinters();

    Task<PrintResult> PrintAsync(string printerName, byte[] payload, string jobId, CancellationToken cancellationToken);
}
