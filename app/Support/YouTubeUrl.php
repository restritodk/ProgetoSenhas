<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class YouTubeUrl
{
    /**
     * Official YouTube hostnames only (exact host match after parse_url).
     *
     * @var list<string>
     */
    public const ALLOWED_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'music.youtube.com',
        'youtu.be',
        'www.youtu.be',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];

    public const VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    /**
     * Parse and normalize a YouTube URL. Does not fetch remote content (no SSRF).
     *
     * @return array{video_id: string, normalized_url: string}
     *
     * @throws ValidationException
     */
    public static function parse(string $input): array
    {
        $raw = trim($input);

        if ($raw === '') {
            throw ValidationException::withMessages([
                'youtube_url' => 'Informe o link do YouTube.',
            ]);
        }

        if (self::looksLikeHtmlOrEmbed($raw)) {
            throw ValidationException::withMessages([
                'youtube_url' => 'Cole apenas a URL do vídeo, não HTML ou código de embed.',
            ]);
        }

        $candidate = self::ensureScheme($raw);
        $parts = parse_url($candidate);

        if ($parts === false || ! is_array($parts)) {
            throw ValidationException::withMessages([
                'youtube_url' => 'URL do YouTube inválida.',
            ]);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ValidationException::withMessages([
                'youtube_url' => 'Use uma URL http ou https do YouTube.',
            ]);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || ! in_array($host, self::ALLOWED_HOSTS, true)) {
            throw ValidationException::withMessages([
                'youtube_url' => 'Somente links oficiais do YouTube são aceitos.',
            ]);
        }

        $videoId = self::extractVideoId($host, $parts);

        if ($videoId === null || ! preg_match(self::VIDEO_ID_PATTERN, $videoId)) {
            throw ValidationException::withMessages([
                'youtube_url' => 'Não foi possível identificar o ID do vídeo do YouTube.',
            ]);
        }

        return [
            'video_id' => $videoId,
            'normalized_url' => 'https://www.youtube.com/watch?v='.$videoId,
        ];
    }

    public static function tryParse(string $input): ?array
    {
        try {
            return self::parse($input);
        } catch (ValidationException) {
            return null;
        }
    }

    public static function isValidVideoId(string $videoId): bool
    {
        return (bool) preg_match(self::VIDEO_ID_PATTERN, $videoId);
    }

    public static function thumbnailUrl(string $videoId): ?string
    {
        if (! self::isValidVideoId($videoId)) {
            return null;
        }

        return 'https://i.ytimg.com/vi/'.$videoId.'/hqdefault.jpg';
    }

    /**
     * Embed URL for the TV player. Built only from a validated video ID.
     * Uses youtube-nocookie for privacy-enhanced embed when compatible with IFrame API.
     */
    public static function embedUrl(string $videoId, string $origin = ''): ?string
    {
        if (! self::isValidVideoId($videoId)) {
            return null;
        }

        $params = [
            'autoplay' => 1,
            'mute' => 1,
            'controls' => 0,
            'rel' => 0,
            'modestbranding' => 1,
            'playsinline' => 1,
            'enablejsapi' => 1,
        ];

        if ($origin !== '') {
            $params['origin'] = $origin;
        }

        return 'https://www.youtube-nocookie.com/embed/'.$videoId.'?'.http_build_query($params);
    }

    public static function looksLikeHtmlOrEmbed(string $raw): bool
    {
        $lower = strtolower($raw);

        return str_contains($raw, '<')
            || str_contains($raw, '>')
            || str_contains($lower, '<iframe')
            || str_contains($lower, 'javascript:')
            || str_contains($lower, 'data:');
    }

    private static function ensureScheme(string $raw): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $raw) === 1) {
            return $raw;
        }

        return 'https://'.$raw;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private static function extractVideoId(string $host, array $parts): ?string
    {
        $path = (string) ($parts['path'] ?? '');
        $query = [];
        if (isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $isShortHost = $host === 'youtu.be' || $host === 'www.youtu.be';

        if ($isShortHost) {
            $segment = trim($path, '/');
            $id = explode('/', $segment)[0] ?? '';

            return $id !== '' ? $id : null;
        }

        if (isset($query['v']) && is_string($query['v']) && $query['v'] !== '') {
            return $query['v'];
        }

        if (preg_match('#/(embed|shorts|live|v)/([A-Za-z0-9_-]{11})#', $path, $matches) === 1) {
            return $matches[2];
        }

        return null;
    }
}
