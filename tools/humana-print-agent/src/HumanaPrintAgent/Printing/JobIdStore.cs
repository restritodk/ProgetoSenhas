using System.Collections.Concurrent;
using System.Text.Json;

namespace HumanaPrintAgent.Printing;

public sealed class JobIdStore
{
    private readonly ConcurrentDictionary<string, DateTimeOffset> _memory = new(StringComparer.Ordinal);
    private readonly string _path;
    private readonly object _fileLock = new();
    private readonly TimeSpan _ttl = TimeSpan.FromHours(24);

    public JobIdStore(string path)
    {
        _path = path;
        Directory.CreateDirectory(Path.GetDirectoryName(path)!);
        Load();
    }

    public bool TryBegin(string jobId)
    {
        Cleanup();
        if (_memory.ContainsKey(jobId))
        {
            return false;
        }

        _memory[jobId] = DateTimeOffset.UtcNow;
        Persist();
        return true;
    }

    public void MarkSeen(string jobId)
    {
        _memory[jobId] = DateTimeOffset.UtcNow;
        Persist();
    }

    private void Cleanup()
    {
        var cutoff = DateTimeOffset.UtcNow - _ttl;
        foreach (var pair in _memory)
        {
            if (pair.Value < cutoff)
            {
                _memory.TryRemove(pair.Key, out _);
            }
        }
    }

    private void Load()
    {
        if (!File.Exists(_path))
        {
            return;
        }

        try
        {
            var json = File.ReadAllText(_path);
            var data = JsonSerializer.Deserialize<Dictionary<string, DateTimeOffset>>(json);
            if (data is null)
            {
                return;
            }

            foreach (var pair in data)
            {
                _memory[pair.Key] = pair.Value;
            }
        }
        catch
        {
            // ignore corrupt store
        }
    }

    private void Persist()
    {
        lock (_fileLock)
        {
            var json = JsonSerializer.Serialize(_memory);
            File.WriteAllText(_path, json);
        }
    }
}
