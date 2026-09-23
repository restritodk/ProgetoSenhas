using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using HumanaPrintAgent.Models;

namespace HumanaPrintAgent.Security;

public static class GrantSigner
{
    public static string CanonicalJson(PrintGrant grant)
    {
        using var doc = JsonDocument.Parse(JsonSerializer.Serialize(grant, SerializerOptions()));
        return WriteCanonical(doc.RootElement);
    }

    public static string CanonicalJson(JsonElement element) => WriteCanonical(element);

    public static string Sign(PrintGrant grant, string secret)
    {
        var bytes = Encoding.UTF8.GetBytes(CanonicalJson(grant));
        using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(secret));
        return Convert.ToHexString(hmac.ComputeHash(bytes)).ToLowerInvariant();
    }

    public static string Sign(JsonElement grantElement, string secret)
    {
        var bytes = Encoding.UTF8.GetBytes(CanonicalJson(grantElement));
        using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(secret));
        return Convert.ToHexString(hmac.ComputeHash(bytes)).ToLowerInvariant();
    }

    public static bool Verify(JsonElement grantElement, string signature, string secret)
    {
        if (string.IsNullOrWhiteSpace(signature) || string.IsNullOrWhiteSpace(secret))
        {
            return false;
        }

        var expected = Sign(grantElement, secret);
        return CryptographicOperations.FixedTimeEquals(
            Encoding.UTF8.GetBytes(expected),
            Encoding.UTF8.GetBytes(signature.Trim().ToLowerInvariant()));
    }

    private static JsonSerializerOptions SerializerOptions() => new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.CamelCase,
        DefaultIgnoreCondition = System.Text.Json.Serialization.JsonIgnoreCondition.WhenWritingNull,
    };

    private static string WriteCanonical(JsonElement element)
    {
        using var stream = new MemoryStream();
        using (var writer = new Utf8JsonWriter(stream))
        {
            WriteElement(writer, element);
        }

        return Encoding.UTF8.GetString(stream.ToArray());
    }

    private static void WriteElement(Utf8JsonWriter writer, JsonElement element)
    {
        switch (element.ValueKind)
        {
            case JsonValueKind.Object:
                writer.WriteStartObject();
                foreach (var property in element.EnumerateObject().OrderBy(p => p.Name, StringComparer.Ordinal))
                {
                    writer.WritePropertyName(property.Name);
                    WriteElement(writer, property.Value);
                }
                writer.WriteEndObject();
                break;
            case JsonValueKind.Array:
                writer.WriteStartArray();
                foreach (var item in element.EnumerateArray())
                {
                    WriteElement(writer, item);
                }
                writer.WriteEndArray();
                break;
            default:
                element.WriteTo(writer);
                break;
        }
    }
}
