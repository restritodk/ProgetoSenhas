using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using HumanaPrintAgent;
using HumanaPrintAgent.Models;
using HumanaPrintAgent.Printing;
using HumanaPrintAgent.Security;
using Xunit;

namespace HumanaPrintAgent.Tests;

public class EscPosFormatterTests
{
    [Theory]
    [InlineData("58")]
    [InlineData("80")]
    public void Formats_ticket_bytes_for_paper_widths(string width)
    {
        var bytes = EscPosFormatter.FormatTicket(new TicketPayload
        {
            ClinicName = "Clinica Teste",
            DisplayCode = "N004",
            TypeLabel = "Atendimento Normal",
            UnitName = "Recepcao",
            IssuedAtLabel = "23/09/2026 10:14",
            Message = "Aguarde sua senha ser chamada no painel.",
        }, width, autoCut: true, isTest: false);

        Assert.NotEmpty(bytes);
        Assert.Contains((byte)0x1B, bytes); // ESC
        Assert.DoesNotContain((byte)'@', Encoding.ASCII.GetBytes("token")); // sanity
    }
}

public class JobIdStoreTests
{
    [Fact]
    public void Duplicate_job_is_rejected()
    {
        var path = Path.Combine(Path.GetTempPath(), "humana-print-jobs-" + Guid.NewGuid().ToString("N") + ".json");
        var store = new JobIdStore(path);

        Assert.True(store.TryBegin("ticket-print-1"));
        Assert.False(store.TryBegin("ticket-print-1"));
    }
}

public class GrantSignerTests
{
    [Fact]
    public void Signature_is_stable_for_sorted_json()
    {
        var secret = "dev-only-pairing-secret-change-me-32chars-min";
        var grant = new PrintGrant
        {
            V = 1,
            Action = "print_ticket",
            JobId = "ticket-print-10",
            Printer = "MOCK Thermal 80mm",
            PaperWidth = "80",
            AutoCut = true,
            Ticket = new TicketPayload
            {
                DisplayCode = "N004",
                TypeLabel = "Atendimento Normal",
                ClinicName = "Clinica",
                UnitName = "Recepcao",
                IssuedAtLabel = "23/09/2026 10:14",
                Message = "Aguarde",
            },
            Exp = 1893456000,
            Nonce = "abc123",
        };

        var json = JsonSerializer.Serialize(grant, new JsonSerializerOptions { PropertyNamingPolicy = JsonNamingPolicy.CamelCase });
        using var doc = JsonDocument.Parse(json);
        var signature = GrantSigner.Sign(doc.RootElement, secret);
        Assert.True(GrantSigner.Verify(doc.RootElement, signature, secret));
        Assert.False(GrantSigner.Verify(doc.RootElement, signature, secret + "x"));
    }
}

public class MockPrintProviderTests
{
    [Fact]
    public async Task Mock_writes_file_and_rejects_unknown_printer()
    {
        var dir = Path.Combine(Path.GetTempPath(), "humana-mock-" + Guid.NewGuid().ToString("N"));
        var provider = new MockPrintProvider(dir);
        var ok = await provider.PrintAsync("MOCK Thermal 80mm", [0x1B, 0x40], "job-1", CancellationToken.None);
        Assert.True(ok.Printed);
        Assert.Equal("ok", ok.Status);

        var fail = await provider.PrintAsync("Missing", [0x1B], "job-2", CancellationToken.None);
        Assert.False(fail.Printed);
    }
}

public class ListenEndpointTests
{
    [Fact]
    public void Local_mode_always_binds_loopback()
    {
        var url = ListenEndpoint.ResolveUrl(new PrintOptions
        {
            ListenMode = "Local",
            Port = 17321,
            BindHost = "192.168.1.50",
        });

        Assert.Equal("http://127.0.0.1:17321", url);
        Assert.True(ListenEndpoint.IsLoopbackUrl(url));
    }

    [Fact]
    public void Lan_mode_is_not_default_and_requires_bind_host()
    {
        Assert.Equal(ListenEndpoint.LocalMode, new PrintOptions().ListenMode);

        Assert.Throws<InvalidOperationException>(() => ListenEndpoint.ResolveUrl(new PrintOptions
        {
            ListenMode = "Lan",
            Port = 17321,
            BindHost = "",
        }));

        var url = ListenEndpoint.ResolveUrl(new PrintOptions
        {
            ListenMode = "Lan",
            Port = 17321,
            BindHost = "192.168.1.50",
        });

        Assert.Equal("http://192.168.1.50:17321", url);
        Assert.False(ListenEndpoint.IsLoopbackUrl(url));
    }
}
