<?php

namespace Tests\Unit;

use App\Services\GoogleTextToSpeechService;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase as ApplicationTestCase;

class GoogleTextToSpeechServiceTest extends ApplicationTestCase
{
    public function test_missing_credentials_do_not_call_google(): void
    {
        config([
            'tv_tts.enabled' => true,
            'tv_tts.driver' => 'google',
            'services.google_tts.credentials' => '',
        ]);

        $called = false;
        $service = (new GoogleTextToSpeechService)->usingClientFactory(function () use (&$called): object {
            $called = true;

            throw new RuntimeException('should not run');
        });

        $this->assertFalse($service->isAvailable());
        $this->assertSame('', $service->synthesizeWav('Senha P zero um sete.'));
        $this->assertFalse($called);
    }

    public function test_google_failure_returns_empty_audio_closes_client_and_redacts_secrets(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'humana-tts-'.uniqid('', true).'.json';
        file_put_contents($path, '{"private_key":"SECRET-KEY-VALUE"}');

        $state = (object) ['closed' => false];

        try {
            config([
                'tv_tts.enabled' => true,
                'tv_tts.driver' => 'google',
                'services.google_tts.credentials' => $path,
                'services.google_tts.language_code' => 'pt-BR',
            ]);

            Event::fake([MessageLogged::class]);

            $service = (new GoogleTextToSpeechService)->usingClientFactory(function () use ($state, $path): object {
                return new class($state, $path)
                {
                    public function __construct(private object $state, private string $path) {}

                    public function synthesizeSpeech(mixed $request): never
                    {
                        throw new RuntimeException(
                            'api down '.$this->path.' "private_key":"SECRET-KEY-VALUE" -----BEGIN PRIVATE KEY-----ABC-----END PRIVATE KEY-----'
                        );
                    }

                    public function close(): void
                    {
                        $this->state->closed = true;
                    }
                };
            });

            $this->assertSame('', $service->synthesizeWav('Senha P zero um sete. Dirigir-se à mesa quatro.'));
            $this->assertTrue($state->closed);

            Event::assertDispatched(MessageLogged::class, function (MessageLogged $event) use ($path): bool {
                $message = (string) ($event->context['message'] ?? '');

                return $event->level === 'warning'
                    && $event->message === 'tv.tts.google_failed'
                    && $message !== ''
                    && ! str_contains($message, $path)
                    && ! str_contains($message, 'SECRET-KEY-VALUE')
                    && ! str_contains($message, 'BEGIN PRIVATE KEY');
            });
        } finally {
            @unlink($path);
        }
    }
}
