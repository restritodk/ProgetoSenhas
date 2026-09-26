<?php

namespace App\Services;

use App\Contracts\TvSpeechSynthesizer;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\Client\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\SynthesizeSpeechRequest;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleTextToSpeechService implements TvSpeechSynthesizer
{
    /** @var (callable(): object)|null */
    private $clientFactory = null;

    /**
     * @param  callable(): object  $clientFactory
     */
    public function usingClientFactory(callable $clientFactory): self
    {
        $this->clientFactory = $clientFactory;

        return $this;
    }

    public function isAvailable(): bool
    {
        return (bool) config('tv_tts.enabled', true)
            && config('tv_tts.driver') === 'google'
            && $this->hasCredentialsFile();
    }

    public function hasCredentialsFile(): bool
    {
        $path = $this->credentialsPath();

        return $path !== '' && is_file($path) && is_readable($path);
    }

    public function fileExtension(): string
    {
        return 'mp3';
    }

    public function contentType(): string
    {
        return 'audio/mpeg';
    }

    public function synthesizeWav(string $text): string
    {
        if (! $this->isAvailable()) {
            return '';
        }

        return $this->synthesizeMp3($text);
    }

    /**
     * MP3 bytes for a server-built phrase. Empty string on failure.
     * Does not require the TV driver flag so the manual artisan check can run.
     */
    public function synthesizeMp3(string $text): string
    {
        $text = trim($text);
        if ($text === '' || ! $this->hasCredentialsFile()) {
            return '';
        }

        $client = null;

        try {
            $client = $this->makeClient();
            $voice = (new VoiceSelectionParams)->setLanguageCode($this->languageCode());
            $voiceName = $this->voiceName();
            if ($voiceName !== '') {
                $voice->setName($voiceName);
            }

            $audioConfig = (new AudioConfig)
                ->setAudioEncoding(AudioEncoding::MP3)
                ->setSpeakingRate($this->speakingRate());

            $request = (new SynthesizeSpeechRequest)
                ->setInput((new SynthesisInput)->setText(mb_substr($text, 0, 500)))
                ->setVoice($voice)
                ->setAudioConfig($audioConfig);

            $response = $client->synthesizeSpeech($request);
            $audio = $response->getAudioContent();

            return is_string($audio) && $audio !== '' ? $audio : '';
        } catch (Throwable $exception) {
            Log::warning('tv.tts.google_failed', [
                'exception' => $exception::class,
                'message' => $this->redact($exception->getMessage()),
            ]);

            return '';
        } finally {
            if (is_object($client) && method_exists($client, 'close')) {
                try {
                    $client->close();
                } catch (Throwable) {
                    // Closing the client must not surface credentials or mask the original failure.
                }
            }
        }
    }

    private function makeClient(): object
    {
        if (is_callable($this->clientFactory)) {
            return ($this->clientFactory)();
        }

        $credentials = new ServiceAccountCredentials(
            TextToSpeechClient::$serviceScopes,
            $this->credentialsPath(),
        );

        return new TextToSpeechClient([
            'credentials' => $credentials,
        ]);
    }

    private function credentialsPath(): string
    {
        $path = config('services.google_tts.credentials');
        if (! is_string($path)) {
            return '';
        }

        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            return '';
        }

        return $path;
    }

    private function languageCode(): string
    {
        $code = trim((string) config('services.google_tts.language_code', 'pt-BR'));

        return $code !== '' ? $code : 'pt-BR';
    }

    private function voiceName(): string
    {
        return trim((string) config('services.google_tts.voice_name', ''));
    }

    private function speakingRate(): float
    {
        $rate = (float) config('services.google_tts.speaking_rate', 1.0);

        return max(0.25, min(4.0, $rate));
    }

    private function redact(string $message): string
    {
        $path = $this->credentialsPath();
        if ($path !== '') {
            $message = str_replace($path, '[credentials]', $message);
        }

        $message = preg_replace('/-----BEGIN [^-]+-----.*?-----END [^-]+-----/s', '[redacted]', $message) ?? $message;
        $message = preg_replace('/"private_key"\s*:\s*"[^"]*"/', '"private_key":"[redacted]"', $message) ?? $message;

        return mb_substr($message, 0, 240);
    }
}
