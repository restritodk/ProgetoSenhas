<?php

namespace App\Console\Commands;

use App\Services\GoogleTextToSpeechService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TestGoogleTts extends Command
{
    protected $signature = 'app:test-google-tts';

    protected $description = 'Testa a integração do humanaClinica com Google Cloud Text-to-Speech';

    public function handle(GoogleTextToSpeechService $speech): int
    {
        try {
            $this->info('Iniciando teste do Google Cloud Text-to-Speech...');

            if (! $speech->hasCredentialsFile()) {
                $this->error('Arquivo de credenciais do Google não encontrado.');

                return self::FAILURE;
            }

            $this->info('Solicitando áudio ao Google...');

            $audio = $speech->synthesizeMp3(
                'Senha N zero zero um. Dirigir-se ao guichê dois.'
            );

            if ($audio === '') {
                $this->newLine();
                $this->error('Falha ao gerar o áudio.');

                return self::FAILURE;
            }

            Storage::disk('local')->put('tts/teste-senha.mp3', $audio);

            $this->newLine();
            $this->info('Áudio gerado com sucesso!');
            $this->line('Arquivo: '.Storage::disk('local')->path('tts/teste-senha.mp3'));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->newLine();
            $this->error('Falha ao gerar o áudio.');

            return self::FAILURE;
        }
    }
}
