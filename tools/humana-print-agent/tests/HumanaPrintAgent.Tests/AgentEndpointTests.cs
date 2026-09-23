using System.Net;
using System.Net.Http.Json;
using System.Text.Json;
using HumanaPrintAgent.Models;
using HumanaPrintAgent.Security;
using Microsoft.AspNetCore.Hosting;
using Microsoft.AspNetCore.Mvc.Testing;
using Microsoft.Extensions.Configuration;
using Xunit;

namespace HumanaPrintAgent.Tests;

public class AgentEndpointTests : IClassFixture<WebApplicationFactory<Program>>
{
    private readonly WebApplicationFactory<Program> _factory;

    public AgentEndpointTests(WebApplicationFactory<Program> factory)
    {
        var jobPath = Path.Combine(Path.GetTempPath(), "humana-agent-jobs-" + Guid.NewGuid().ToString("N") + ".json");
        _factory = factory.WithWebHostBuilder(builder =>
        {
            builder.UseEnvironment("Development");
            builder.ConfigureAppConfiguration((_, config) =>
            {
                config.AddInMemoryCollection(new Dictionary<string, string?>
                {
                    ["Print:Mode"] = "Mock",
                    ["Print:PairingSecret"] = "dev-only-pairing-secret-change-me-32chars-min",
                    ["Print:JobStorePath"] = jobPath,
                });
            });
        });
    }

    [Fact]
    public async Task Health_reports_mock_mode()
    {
        var client = _factory.CreateClient();
        var response = await client.GetAsync("/health");
        response.EnsureSuccessStatusCode();
        var json = await response.Content.ReadFromJsonAsync<JsonElement>();
        Assert.Equal("ok", json.GetProperty("status").GetString());
        Assert.Equal("Mock", json.GetProperty("mode").GetString());
    }

    [Fact]
    public async Task Duplicate_job_does_not_print_twice()
    {
        var client = _factory.CreateClient();
        var secret = "dev-only-pairing-secret-change-me-32chars-min";
        var grant = BuildGrant("print_ticket", "ticket-print-77");
        var signature = GrantSigner.Sign(grant, secret);
        var body = new { grant, signature };

        var first = await client.PostAsJsonAsync("/print/ticket", body);
        first.EnsureSuccessStatusCode();
        var firstResult = await first.Content.ReadFromJsonAsync<PrintResult>();
        Assert.True(firstResult!.Printed);

        var second = await client.PostAsJsonAsync("/print/ticket", body);
        second.EnsureSuccessStatusCode();
        var secondResult = await second.Content.ReadFromJsonAsync<PrintResult>();
        Assert.Equal("duplicate", secondResult!.Status);
        Assert.False(secondResult.Printed);
    }

    [Fact]
    public async Task Print_test_accepts_print_test_action()
    {
        var client = _factory.CreateClient();
        var secret = "dev-only-pairing-secret-change-me-32chars-min";
        var grant = BuildGrant("print_test", "print-test-1-" + Guid.NewGuid().ToString("N"));
        var signature = GrantSigner.Sign(grant, secret);

        var response = await client.PostAsJsonAsync("/print/test", new { grant, signature });
        response.EnsureSuccessStatusCode();
        var result = await response.Content.ReadFromJsonAsync<PrintResult>();
        Assert.True(result!.Printed);
    }

    [Fact]
    public async Task Invalid_signature_is_rejected()
    {
        var client = _factory.CreateClient();
        var grant = BuildGrant("list_printers", "list-1");
        var response = await client.PostAsJsonAsync("/printers", new { grant, signature = "deadbeef" });
        Assert.Equal(HttpStatusCode.Unauthorized, response.StatusCode);
    }

    private static PrintGrant BuildGrant(string action, string jobId) => new()
    {
        V = 1,
        Action = action,
        JobId = jobId,
        Printer = "MOCK Thermal 80mm",
        PaperWidth = "80",
        AutoCut = true,
        Ticket = new TicketPayload
        {
            DisplayCode = action == "print_test" ? "" : "N004",
            TypeLabel = action == "print_test" ? "TESTE DE IMPRESSAO" : "Atendimento Normal",
            ClinicName = "Clinica",
            UnitName = action == "print_test" ? "Totem: Recepcao" : "Recepcao",
            IssuedAtLabel = "23/09/2026 10:14",
            Message = action == "print_test"
                ? "Impressora: MOCK Thermal 80mm\nConfiguracao de impressao funcionando."
                : "Aguarde",
        },
        Exp = DateTimeOffset.UtcNow.AddMinutes(5).ToUnixTimeSeconds(),
        Nonce = Guid.NewGuid().ToString("N")[..24],
    };
}
