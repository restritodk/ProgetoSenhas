using System.Globalization;
using System.Text;
using HumanaPrintAgent.Models;

namespace HumanaPrintAgent.Printing;

public static class EscPosFormatter
{
    private static readonly Encoding Latin1 = Encoding.GetEncoding("ISO-8859-1");

    public static byte[] FormatTicket(TicketPayload ticket, string paperWidth, bool autoCut, bool isTest)
    {
        var chars = paperWidth == "58" ? 32 : 42;
        using var ms = new MemoryStream();

        void Write(params byte[] bytes) => ms.Write(bytes);
        void Text(string value) => Write(Latin1.GetBytes(Sanitize(value)));
        void Line(string value = "") { Text(value); Write(0x0A); }
        void Center() => Write(0x1B, 0x61, 0x01);
        void Big(bool on) => Write(0x1D, 0x21, (byte)(on ? 0x11 : 0x00));
        void Bold(bool on) => Write(0x1B, 0x45, (byte)(on ? 0x01 : 0x00));

        // Initialize
        Write(0x1B, 0x40);
        Center();
        Bold(true);
        Line(Truncate(ticket.ClinicName, chars));
        Bold(false);
        Line();

        if (isTest)
        {
            Bold(true);
            Line(Truncate(string.IsNullOrWhiteSpace(ticket.TypeLabel) ? "TESTE DE IMPRESSAO" : ticket.TypeLabel, chars));
            Bold(false);
            Line();
            if (!string.IsNullOrWhiteSpace(ticket.UnitName))
            {
                Line(Truncate(ticket.UnitName, chars));
            }
            foreach (var wrap in Wrap(ticket.Message, chars))
            {
                Line(wrap);
            }
            Line();
            Line(Truncate(ticket.IssuedAtLabel, chars));
        }
        else
        {
            Line("SENHA DE ATENDIMENTO");
            Line();
            Big(true);
            Bold(true);
            Line(Truncate(ticket.DisplayCode, Math.Max(8, chars / 2)));
            Bold(false);
            Big(false);
            Line();
            Line(Truncate(ticket.TypeLabel, chars));
            if (!string.IsNullOrWhiteSpace(ticket.UnitName))
            {
                Line(Truncate(ticket.UnitName, chars));
            }
            Line(Truncate(ticket.IssuedAtLabel, chars));
            Line();
            foreach (var wrap in Wrap(ticket.Message, chars))
            {
                Line(wrap);
            }
        }

        Line();
        Center();
        Line(new string('-', Math.Min(chars, 32)));
        Line();
        Line();

        if (autoCut)
        {
            // Partial cut; ignored by printers without cutter.
            Write(0x1D, 0x56, 0x01);
        }

        return ms.ToArray();
    }

    private static string Sanitize(string value)
    {
        var normalized = value.Normalize(NormalizationForm.FormD);
        var sb = new StringBuilder(normalized.Length);
        foreach (var ch in normalized)
        {
            var category = CharUnicodeInfo.GetUnicodeCategory(ch);
            if (category != UnicodeCategory.NonSpacingMark)
            {
                sb.Append(ch);
            }
        }

        return sb.ToString().Normalize(NormalizationForm.FormC);
    }

    private static string Truncate(string value, int max)
    {
        value = Sanitize(value).Trim();
        return value.Length <= max ? value : value[..max];
    }

    private static IEnumerable<string> Wrap(string value, int width)
    {
        value = Sanitize(value).Trim();
        if (value.Length == 0)
        {
            yield break;
        }

        while (value.Length > width)
        {
            var split = value.LastIndexOf(' ', width);
            if (split <= 0)
            {
                split = width;
            }

            yield return value[..split].Trim();
            value = value[split..].TrimStart();
        }

        if (value.Length > 0)
        {
            yield return value;
        }
    }
}
