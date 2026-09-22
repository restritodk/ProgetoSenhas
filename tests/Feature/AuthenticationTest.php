<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Bem-vindo de volta')
            ->assertSee('humanaClinica')
            ->assertDontSee('PROGETOSENHAS')
            ->assertDontSee('progetoSenhas');
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_valid_user_can_login_and_session_is_regenerated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $oldSession = $this->app['session']->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, $this->app['session']->getId());
    }

    public function test_invalid_password_is_rejected_without_user_enumeration(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret-password')]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_rate_limit_blocks_excessive_attempts(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $response = $this->post('/login', [
                'email' => 'unknown@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response->assertStatus(429);
    }

    public function test_public_registration_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
