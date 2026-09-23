using System.Runtime.InteropServices;
using System.Runtime.Versioning;
using HumanaPrintAgent.Models;

namespace HumanaPrintAgent.Printing;

[SupportedOSPlatform("windows")]
public sealed class WindowsSpoolerProvider : IPrintProvider
{
    public string ModeName => "Spooler";

    public IReadOnlyList<PrinterInfo> ListPrinters()
    {
        var result = new List<PrinterInfo>();
        if (!OperatingSystem.IsWindows())
        {
            return result;
        }

        foreach (string printer in System.Drawing.Printing.PrinterSettings.InstalledPrinters)
        {
            var settings = new System.Drawing.Printing.PrinterSettings { PrinterName = printer };
            result.Add(new PrinterInfo
            {
                Name = printer,
                IsDefault = settings.IsDefaultPrinter,
                Status = "available",
            });
        }

        return result;
    }

    public Task<PrintResult> PrintAsync(string printerName, byte[] payload, string jobId, CancellationToken cancellationToken)
    {
        if (!OperatingSystem.IsWindows())
        {
            return Task.FromResult(new PrintResult
            {
                Status = "error",
                Printed = false,
                JobId = jobId,
                Detail = "Windows spooler is only available on Windows.",
            });
        }

        var printers = ListPrinters();
        if (!printers.Any(p => string.Equals(p.Name, printerName, StringComparison.OrdinalIgnoreCase)))
        {
            return Task.FromResult(new PrintResult
            {
                Status = "error",
                Printed = false,
                JobId = jobId,
                Detail = "Configured printer was not found on this computer.",
            });
        }

        try
        {
            RawPrinterHelper.SendBytesToPrinter(printerName, payload);
            return Task.FromResult(new PrintResult
            {
                Status = "ok",
                Printed = true,
                JobId = jobId,
            });
        }
        catch (Exception ex)
        {
            return Task.FromResult(new PrintResult
            {
                Status = "error",
                Printed = false,
                JobId = jobId,
                Detail = ex.Message,
            });
        }
    }
}

[SupportedOSPlatform("windows")]
internal static class RawPrinterHelper
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private class DOCINFO
    {
        [MarshalAs(UnmanagedType.LPWStr)]
        public string pDocName = "HumanaTicket";
        [MarshalAs(UnmanagedType.LPWStr)]
        public string? pOutputFile;
        [MarshalAs(UnmanagedType.LPWStr)]
        public string pDataType = "RAW";
    }

    [DllImport("winspool.drv", SetLastError = true, CharSet = CharSet.Unicode)]
    private static extern bool OpenPrinter(string pPrinterName, out IntPtr phPrinter, IntPtr pDefault);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true, CharSet = CharSet.Unicode)]
    private static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In] DOCINFO di);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true)]
    private static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);

    public static void SendBytesToPrinter(string printerName, byte[] bytes)
    {
        if (!OpenPrinter(printerName, out var printer, IntPtr.Zero))
        {
            throw new InvalidOperationException("Unable to open printer.");
        }

        try
        {
            var doc = new DOCINFO();
            if (!StartDocPrinter(printer, 1, doc))
            {
                throw new InvalidOperationException("Unable to start print document.");
            }

            try
            {
                if (!StartPagePrinter(printer))
                {
                    throw new InvalidOperationException("Unable to start print page.");
                }

                var unmanaged = Marshal.AllocHGlobal(bytes.Length);
                try
                {
                    Marshal.Copy(bytes, 0, unmanaged, bytes.Length);
                    if (!WritePrinter(printer, unmanaged, bytes.Length, out _))
                    {
                        throw new InvalidOperationException("Unable to write to printer.");
                    }
                }
                finally
                {
                    Marshal.FreeHGlobal(unmanaged);
                    EndPagePrinter(printer);
                }
            }
            finally
            {
                EndDocPrinter(printer);
            }
        }
        finally
        {
            ClosePrinter(printer);
        }
    }
}
