<?php

namespace Tests\Support;

use App\Contracts\TvSpeechSynthesizer;

/**
 * Deterministic in-process WAV synthesizer for feature tests (no espeak-ng).
 */
class FakeTvSpeechSynthesizer implements TvSpeechSynthesizer
{
    public bool $available = true;

    public string $extension = 'wav';

    public string $mimeType = 'audio/wav';

    public int $synthesizeCalls = 0;

    /** @var list<string> */
    public array $texts = [];

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function fileExtension(): string
    {
        return $this->extension;
    }

    public function contentType(): string
    {
        return $this->mimeType;
    }

    public function synthesizeWav(string $text): string
    {
        $this->synthesizeCalls++;
        $this->texts[] = $text;

        return $this->minimalWav();
    }

    /**
     * Minimal valid PCM WAV (44-byte header + silence).
     */
    public function minimalWav(): string
    {
        $dataSize = 160;
        $sampleRate = 22050;
        $bitsPerSample = 16;
        $channels = 1;
        $byteRate = (int) ($sampleRate * $channels * $bitsPerSample / 8);
        $blockAlign = (int) ($channels * $bitsPerSample / 8);

        return 'RIFF'
            .pack('V', 36 + $dataSize)
            .'WAVEfmt '
            .pack('V', 16)
            .pack('v', 1)
            .pack('v', $channels)
            .pack('V', $sampleRate)
            .pack('V', $byteRate)
            .pack('v', $blockAlign)
            .pack('v', $bitsPerSample)
            .'data'
            .pack('V', $dataSize)
            .str_repeat("\0", $dataSize);
    }
}
