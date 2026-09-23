using HumanaPrintAgent.Models;

namespace HumanaPrintAgent.Printing;

public sealed class MockPrintProvider : IPrintProvider
{
    private readonly string _outputDirectory;

    public MockPrintProvider(string outputDirectory)
    {
        _outputDirectory = outputDirectory;
        Directory.CreateDirectory(_outputDirectory);
    }

    public string ModeName => "Mock";

    public IReadOnlyList<PrinterInfo> ListPrinters() =>
    [
        new PrinterInfo { Name = "MOCK Thermal 80mm", IsDefault = true, Status = "available" },
        new PrinterInfo { Name = "MOCK Thermal 58mm", IsDefault = false, Status = "available" },
    ];

    public async Task<PrintResult> PrintAsync(string printerName, byte[] payload, string jobId, CancellationToken cancellationToken)
    {
        var known = ListPrinters().Any(p => string.Equals(p.Name, printerName, StringComparison.OrdinalIgnoreCase));
        if (!known)
        {
            return new PrintResult
            {
                Status = "error",
                Printed = false,
                JobId = jobId,
                Detail = "Printer not found in mock list.",
            };
        }

        var path = Path.Combine(_outputDirectory, $"{Sanitize(jobId)}.bin");
        await File.WriteAllBytesAsync(path, payload, cancellationToken);
        var textPath = Path.Combine(_outputDirectory, $"{Sanitize(jobId)}.txt");
        await File.WriteAllTextAsync(textPath, $"MOCK PRINT job={jobId} printer={printerName} bytes={payload.Length}", cancellationToken);

        return new PrintResult
        {
            Status = "ok",
            Printed = true,
            JobId = jobId,
            Detail = $"Mock wrote {path}",
        };
    }

    private static string Sanitize(string value)
    {
        foreach (var c in Path.GetInvalidFileNameChars())
        {
            value = value.Replace(c, '_');
        }

        return value;
    }
}
