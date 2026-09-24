<?php

namespace App\Contracts;

interface TvSpeechSynthesizer
{
    public function isAvailable(): bool;

    /**
     * Synthesize spoken audio as WAV binary (PCM). Empty string on failure.
     */
    public function synthesizeWav(string $text): string;
}
