<?php

namespace App\Services\TvTts;

use App\Contracts\TvSpeechSynthesizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Free/local TTS via espeak-ng (no paid API). Requires the binary on the host.
 */
class EspeakNgSynthesizer implements TvSpeechSynthesizer
{
    public function isAvailable(): bool
    {
        if (! config('tv_tts.enabled', true)) {
            return false;
        }

        if (config('tv_tts.driver') !== 'espeak') {
            return false;
        }

        $binary = (string) config('tv_tts.espeak_binary', 'espeak-ng');

        return (bool) Cache::remember('tv-tts:espeak-available:'.$binary, 60, function () use ($binary): bool {
            try {
                $process = new Process([$binary, '--version']);
                $process->setTimeout(3);
                $process->run();

                return $process->isSuccessful();
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public function fileExtension(): string
    {
        return 'wav';
    }

    public function contentType(): string
    {
        return 'audio/wav';
    }

    public function synthesizeWav(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $binary = (string) config('tv_tts.espeak_binary', 'espeak-ng');
        $voice = (string) config('tv_tts.voice', 'pt');
        $rate = max(80, min(250, (int) config('tv_tts.rate', 145)));
        $timeout = max(3, min(30, (int) config('tv_tts.process_timeout_seconds', 8)));

        $tmp = tempnam(sys_get_temp_dir(), 'humana-tts-');
        if ($tmp === false) {
            return '';
        }

        $wav = $tmp.'.wav';
        @unlink($tmp);

        try {
            $process = new Process([
                $binary,
                '-v', $voice,
                '-s', (string) $rate,
                '-w', $wav,
                '--',
                mb_substr($text, 0, 500),
            ]);
            $process->setTimeout($timeout);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($wav)) {
                Log::warning('tv.tts.espeak_failed', [
                    'exit' => $process->getExitCode(),
                    'error' => mb_substr($process->getErrorOutput(), 0, 240),
                ]);

                return '';
            }

            $bytes = file_get_contents($wav);

            return is_string($bytes) && $bytes !== '' ? $bytes : '';
        } catch (\Throwable $exception) {
            Log::warning('tv.tts.espeak_exception', [
                'message' => $exception->getMessage(),
            ]);

            return '';
        } finally {
            if (is_file($wav)) {
                @unlink($wav);
            }
        }
    }
}
