using System.Collections.Concurrent;

namespace HumanaPrintAgent;

/// <summary>Simple fixed-window rate limiter per client key (IP).</summary>
public sealed class RequestRateLimiter
{
    private readonly ConcurrentDictionary<string, Window> _windows = new(StringComparer.Ordinal);
    private readonly int _limit;
    private readonly TimeSpan _window;

    public RequestRateLimiter(int limitPerMinute)
    {
        _limit = Math.Max(1, limitPerMinute);
        _window = TimeSpan.FromMinutes(1);
    }

    public bool IsAllowed(string key)
    {
        var now = DateTimeOffset.UtcNow;
        var window = _windows.AddOrUpdate(
            key,
            _ => new Window(now, 1),
            (_, existing) =>
            {
                if (now - existing.Started > _window)
                {
                    return new Window(now, 1);
                }

                return existing with { Count = existing.Count + 1 };
            });

        return window.Count <= _limit;
    }

    private sealed record Window(DateTimeOffset Started, int Count);
}
