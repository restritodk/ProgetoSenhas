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

    public function test_login_page_renders_email_and_password_fields(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Acesse sua conta')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('id="email"', false)
            ->assertSee('id="password"', false)
            ->assertSee('humanaClinica')
            ->assertDontSee('Manter-me conectado')
            ->assertDontSee('Esqueceu sua senha')
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
            'name' => 'Juliane Souza',
            'password' => Hash::make('secret-password'),
            'role' => UserRole::ADMINISTRATOR,
            'active' => true,
        ]);
        $oldSession = $this->app['session']->getId();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'secret-password']);

        $this->assertAuthenticatedLoginFeedback($response, route('dashboard'), 'Juliane');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, $this->app['session']->getId());
    }

    public function test_invalid_password_is_rejected_without_success_feedback(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret-password')]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login')
            ->assertSessionHasErrors('email')
            ->assertDontSee('Acesso autorizado');
        $this->assertGuest();
    }

    public function test_inactive_user_does_not_receive_success_feedback(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
            'role' => UserRole::ADMINISTRATOR,
            'active' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/login')
            ->assertSessionHasErrors('email')
            ->assertDontSee('Acesso autorizado');
        $this->assertGuest();
    }

    public function test_supervisor_login_targets_dashboard(): void
    {
        $user = User::factory()->create([
            'name' => 'Renan Lima',
            'password' => Hash::make('secret-password'),
            'role' => UserRole::SUPERVISOR,
            'active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $this->assertAuthenticatedLoginFeedback($response, route('dashboard'), 'Renan');
        $this->assertAuthenticatedAs($user);
    }

    public function test_attendant_login_targets_attendant_panel(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria Clara',
            'password' => Hash::make('secret-password'),
            'role' => UserRole::ATTENDANT,
            'active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $this->assertAuthenticatedLoginFeedback($response, route('attendant.panel'), 'Maria');
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_session_and_shows_feedback(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertLoggedOutFeedback($this->post('/logout'));
        $this->assertGuest();
    }

    public function test_protected_route_remains_blocked_after_logout_feedback(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMINISTRATOR]));

        $this->assertLoggedOutFeedback($this->post('/logout'));
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
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
