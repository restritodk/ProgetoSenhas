<?php

namespace App\Console\Commands;

use App\Actions\BootstrapFirstAdministrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

#[Signature('app:bootstrap-first-administrator
    {--email= : E-mail do primeiro administrador}
    {--name=Administrador : Nome do primeiro administrador}
    {--password= : Senha (omitir para digitar de forma oculta no terminal)}
    {--clinic-name= : Nome da clínica inicial}
    {--clinic-slug= : Slug da clínica inicial}
    {--unit-name= : Nome da unidade inicial}
    {--unit-slug= : Slug da unidade inicial}')]
#[Description('Cria o primeiro administrador da clínica (comando one-shot; não roda em migrate/deploy automático)')]
class BootstrapFirstAdministratorCommand extends Command
{
    public function handle(BootstrapFirstAdministrator $bootstrap): int
    {
        if ($bootstrap->administratorExists()) {
            $this->components->error('Já existe um administrador cadastrado.');
            $this->line('Use o painel administrativo (Usuários) para criar novos usuários.');

            return self::FAILURE;
        }

        $email = $this->resolveEmail();
        if ($email === null) {
            return self::FAILURE;
        }

        $password = $this->resolvePassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $clinicName = $this->optionString('clinic-name')
            ?: (string) config('bootstrap.clinic_name', 'Clínica principal');
        $clinicSlug = $this->optionString('clinic-slug')
            ?: (string) config('bootstrap.clinic_slug', 'clinica-principal');
        $unitName = $this->optionString('unit-name')
            ?: (string) config('bootstrap.unit_name', 'Unidade principal');
        $unitSlug = $this->optionString('unit-slug')
            ?: (string) config('bootstrap.unit_slug', 'unidade-principal');

        $name = $this->optionString('name') ?: 'Administrador';

        try {
            $user = $bootstrap->handle([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'clinic_name' => $clinicName,
                'clinic_slug' => $clinicSlug,
                'unit_name' => $unitName,
                'unit_slug' => $unitSlug,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->components->error('Falha ao criar o administrador: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Primeiro administrador criado com sucesso.');
        $this->line('E-mail: '.$user->email);
        $this->line('Clínica ID: '.(string) $user->clinic_id);
        $this->newLine();
        $this->warn('Guarde a senha em local seguro. Este comando não deve ser reexecutado.');

        return self::SUCCESS;
    }

    private function resolveEmail(): ?string
    {
        $email = $this->optionString('email');

        if ($email !== '') {
            return Str::lower($email);
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Informe --email=... (obrigatório em modo não interativo).');

            return null;
        }

        $asked = $this->ask('E-mail do administrador');
        if (! is_string($asked) || trim($asked) === '') {
            $this->components->error('E-mail é obrigatório.');

            return null;
        }

        return Str::lower(trim($asked));
    }

    private function resolvePassword(): ?string
    {
        $fromOption = $this->option('password');
        if (is_string($fromOption) && $fromOption !== '') {
            $this->warn('Senha recebida via --password. Prefira o modo interativo para não deixar a senha no histórico do shell.');

            return $fromOption;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Informe --password=... ou execute em um terminal interativo para digitar a senha ocultamente.');

            return null;
        }

        $password = $this->secret('Senha do administrador (mín. 8 caracteres)');
        if (! is_string($password) || $password === '') {
            $this->components->error('Senha é obrigatória.');

            return null;
        }

        $confirm = $this->secret('Confirme a senha');
        if ($password !== $confirm) {
            $this->components->error('A confirmação da senha não confere.');

            return null;
        }

        return $password;
    }

    private function optionString(string $key): string
    {
        $value = $this->option($key);

        return is_string($value) ? trim($value) : '';
    }
}
