<?php

namespace Tests\Feature;

use App\Actions\BootstrapFirstAdministrator;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BootstrapFirstAdministratorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_first_administrator_with_clinic_and_unit(): void
    {
        $this->artisan('app:bootstrap-first-administrator', [
            '--email' => 'admin@example.test',
            '--name' => 'Admin Inicial',
            '--password' => 'secret-password',
            '--clinic-name' => 'Humana Saúde',
            '--clinic-slug' => 'humana-saude',
            '--unit-name' => 'Hospital Toledo',
            '--unit-slug' => 'hospital-toledo',
        ])
            ->expectsOutputToContain('Primeiro administrador criado com sucesso.')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.test')->first();
        $this->assertNotNull($admin);
        $this->assertSame(UserRole::ADMINISTRATOR, $admin->role);
        $this->assertTrue($admin->active);
        $this->assertNotNull($admin->clinic_id);
        $this->assertTrue($admin->units()->where('slug', 'hospital-toledo')->exists());
        $this->assertTrue(
            $admin->clinic?->ticketTypes()->where('prefix', 'N')->exists() ?? false
        );
    }

    public function test_command_fails_when_administrator_already_exists(): void
    {
        app(BootstrapFirstAdministrator::class)->handle([
            'name' => 'Admin',
            'email' => 'first@example.test',
            'password' => 'secret-password',
            'clinic_name' => 'Clínica A',
            'clinic_slug' => 'clinica-a',
            'unit_name' => 'Unidade A',
            'unit_slug' => 'unidade-a',
        ]);

        $this->artisan('app:bootstrap-first-administrator', [
            '--email' => 'second@example.test',
            '--password' => 'secret-password',
        ])
            ->expectsOutputToContain('Já existe um administrador cadastrado.')
            ->assertFailed();

        $this->assertSame(1, User::query()->where('role', UserRole::ADMINISTRATOR)->count());
        $this->assertNull(User::query()->where('email', 'second@example.test')->first());
    }

    public function test_action_rejects_short_password_and_duplicate_email(): void
    {
        $bootstrap = app(BootstrapFirstAdministrator::class);

        try {
            $bootstrap->handle([
                'name' => 'Admin',
                'email' => 'admin@example.test',
                'password' => 'short',
                'clinic_name' => 'Clínica',
                'clinic_slug' => 'clinica',
                'unit_name' => 'Unidade',
                'unit_slug' => 'unidade',
            ]);
            $this->fail('Expected ValidationException for short password.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
        }

        $bootstrap->handle([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret-password',
            'clinic_name' => 'Clínica',
            'clinic_slug' => 'clinica',
            'unit_name' => 'Unidade',
            'unit_slug' => 'unidade',
        ]);

        $this->expectException(ValidationException::class);
        $bootstrap->handle([
            'name' => 'Outro',
            'email' => 'outro@example.test',
            'password' => 'secret-password',
            'clinic_name' => 'Clínica 2',
            'clinic_slug' => 'clinica-2',
            'unit_name' => 'Unidade 2',
            'unit_slug' => 'unidade-2',
        ]);
    }

    public function test_command_requires_password_in_non_interactive_mode_without_option(): void
    {
        $this->artisan('app:bootstrap-first-administrator', [
            '--email' => 'admin@example.test',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Informe --password=...')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
