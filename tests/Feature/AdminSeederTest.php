<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_administrator_with_clinic_and_authorized_unit(): void
    {
        $this->app->make(AdminSeeder::class)->run();

        $user = User::query()->where('email', 'admin@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame(UserRole::ADMINISTRATOR, $user->role);
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertSame('Clínica principal', $user->clinic?->name);
        $this->assertCount(1, $user->units);
        $this->assertTrue($user->hasAccessToUnit($user->units->first()));
        $this->assertTrue($user->can('manage', $user->clinic));
    }

    public function test_administrator_can_login_after_seed(): void
    {
        $this->app->make(AdminSeeder::class)->run();

        $response = $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'secret-password',
        ]);

        $this->assertAuthenticatedLoginFeedback($response, route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_refuses_to_run_without_bootstrap_credentials(): void
    {
        config([
            'bootstrap.admin_email' => null,
            'bootstrap.admin_password' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BOOTSTRAP_ADMIN_EMAIL e BOOTSTRAP_ADMIN_PASSWORD são obrigatórios.');

        $this->app->make(AdminSeeder::class)->run();
    }

    public function test_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AdminSeeder não pode ser executado em produção.');

        $this->app->make(AdminSeeder::class)->run();
    }
}
