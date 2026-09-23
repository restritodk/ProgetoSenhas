<?php

namespace Tests\Unit;

use App\Support\YouTubeUrl;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class YouTubeUrlTest extends TestCase
{
    #[DataProvider('validUrls')]
    public function test_parses_valid_youtube_urls(string $input, string $expectedId): void
    {
        $parsed = YouTubeUrl::parse($input);

        $this->assertSame($expectedId, $parsed['video_id']);
        $this->assertSame('https://www.youtube.com/watch?v='.$expectedId, $parsed['normalized_url']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function validUrls(): array
    {
        $id = 'dQw4w9WgXcQ';

        return [
            'watch' => ['https://www.youtube.com/watch?v='.$id, $id],
            'watch_http' => ['http://youtube.com/watch?v='.$id, $id],
            'short' => ['https://youtu.be/'.$id, $id],
            'shorts' => ['https://www.youtube.com/shorts/'.$id, $id],
            'embed' => ['https://www.youtube.com/embed/'.$id, $id],
            'mobile' => ['https://m.youtube.com/watch?v='.$id, $id],
            'nocookie' => ['https://www.youtube-nocookie.com/embed/'.$id, $id],
            'without_scheme' => ['youtu.be/'.$id, $id],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_rejects_invalid_inputs(string $input): void
    {
        $this->expectException(ValidationException::class);
        YouTubeUrl::parse($input);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'empty' => [''],
            'iframe' => ['<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'],
            'html' => ['<div>https://www.youtube.com/watch?v=dQw4w9WgXcQ</div>'],
            'fake_domain' => ['https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ'],
            'contains_youtube' => ['https://evil-youtube.com/watch?v=dQw4w9WgXcQ'],
            'vimeo' => ['https://vimeo.com/123456'],
            'javascript' => ['javascript:alert(1)'],
            'no_id' => ['https://www.youtube.com/watch'],
            'short_id' => ['https://youtu.be/abc'],
            'drive' => ['https://drive.google.com/file/d/abc/view'],
        ];
    }

    public function test_thumbnail_and_embed_built_only_from_valid_id(): void
    {
        $id = 'dQw4w9WgXcQ';
        $this->assertSame('https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg', YouTubeUrl::thumbnailUrl($id));
        $embed = YouTubeUrl::embedUrl($id, 'https://example.test');
        $this->assertNotNull($embed);
        $this->assertStringStartsWith('https://www.youtube-nocookie.com/embed/'.$id.'?', $embed);
        $this->assertStringContainsString('mute=1', $embed);
        $this->assertStringContainsString('autoplay=1', $embed);

        $this->assertNull(YouTubeUrl::thumbnailUrl('bad'));
        $this->assertNull(YouTubeUrl::embedUrl('../x'));
    }
}
