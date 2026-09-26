<?php

namespace App\Contracts;

interface TvSpeechSynthesizer
{
    public function isAvailable(): bool;

    /**
     * Spoken audio bytes (WAV or MP3, see fileExtension()). Empty string on failure.
     */
    public function synthesizeWav(string $text): string;

    public function fileExtension(): string;

    public function contentType(): string;
}
