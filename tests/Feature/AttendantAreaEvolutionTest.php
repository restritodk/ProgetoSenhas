<?php

namespace Tests\Feature;

use App\Actions\ClaimDesk;
use App\Actions\CompleteTicketService;
use App\Actions\EnsureClinicRolePermissions;
use App\Actions\OpenDirectConversation;
use App\Actions\SendClinicMessage;
use App\Livewire\AttendantHistory;
use App\Livewire\AttendantMessages;
use App\Livewire\AttendantProfile;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\AttendantPerformanceAnalytics;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Services\OperationalContext;
use App\Support\AttendantNavigation;
use App\TicketStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AttendantAreaEvolutionTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 100;

    public function test_attendant_login_redirects_to_attendant_panel_not_admin(): void
    {
        [$clinic, , , $attendant] = $this->seedClinic();

        $response = $this->post(route('login.store'), [
            'email' => $attendant->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('attendant.panel'));
    }

    public function test_admin_login_redirects_to_dashboard(): void
    {
        [$clinic, $admin] = $this->seedClinic();

        $response = $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_attendant_is_blocked_from_admin_dashboard(): void
    {
        [, , , $attendant] = $this->seedClinic();

        $this->actingAs($attendant)
            ->get(route('dashboard'))
            ->assertRedirect(route('attendant.panel'));
    }

    public function test_attendant_menu_items_and_hides_admin_shortcut(): void
    {
        [, , , $attendant] = $this->seedClinic();

        $labels = collect(AttendantNavigation::items($attendant))->pluck('label')->all();
        $this->assertContains('Dashboard', $labels);
        $this->assertContains('Atendimento', $labels);
        $this->assertContains('Fila da Mesa', $labels);
        $this->assertContains('Histórico', $labels);
        $this->assertContains('Mensagens', $labels);
        $this->assertContains('Perfil', $labels);

        $this->actingAs($attendant)
            ->get(route('attendant.panel'))
            ->assertOk()
            ->assertDontSee('Painel administrativo');
    }

    public function test_main_logo_appears_in_attendant_layout_when_configured(): void
    {
        [$clinic, , , $attendant] = $this->seedClinic();
        Storage::fake('public');

        $path = 'clinic-branding/'.$clinic->id.'/main-logo.png';
        Storage::disk('public')->put($path, 'fake');
        app(ClinicSettings::class)->putPath($clinic, 'main_logo_path', $path);

        $branding = app(ClinicBranding::class)->resolve($clinic);
        $this->assertTrue($branding['has_main_logo']);
        $this->assertNotNull($branding['main_logo_url']);

        $this->actingAs($attendant)
            ->get(route('attendant.dashboard'))
            ->assertOk()
            ->assertSee($branding['main_logo_url'], false);
    }

    public function test_dashboard_counts_only_authenticated_attendant_completions(): void
    {
        [$clinic, , , $attendant] = $this->seedClinic();
        $other = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $unit = Unit::factory()->for($clinic)->create();
        $type = TicketType::factory()->for($clinic)->create();

        $this->makeCompletedTicket($clinic->id, $unit->id, $type->id, $attendant->id);
        $this->makeCompletedTicket($clinic->id, $unit->id, $type->id, $other->id);

        $this->actingAs($attendant);
        $report = app(AttendantPerformanceAnalytics::class)->forAuthenticatedUser('today');

        $completedCard = collect($report['cards'])->firstWhere('key', 'completed');
        $this->assertSame(1, $completedCard['value']);
    }

    public function test_dashboard_does_not_leak_other_clinic(): void
    {
        [$clinicA, , , $attendantA] = $this->seedClinic();
        $clinicB = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinicB);
        $unitB = Unit::factory()->for($clinicB)->create();
        $typeB = TicketType::factory()->for($clinicB)->create();
        $attendantB = User::factory()->create(['clinic_id' => $clinicB->id, 'role' => UserRole::ATTENDANT]);

        $this->makeCompletedTicket($clinicB->id, $unitB->id, $typeB->id, $attendantB->id);

        $this->actingAs($attendantA);
        $report = app(AttendantPerformanceAnalytics::class)->forAuthenticatedUser('today');
        $completedCard = collect($report['cards'])->firstWhere('key', 'completed');
        $this->assertSame(0, $completedCard['value']);
    }

    public function test_history_shows_only_personal_tickets(): void
    {
        [$clinic, , , $attendant] = $this->seedClinic();
        $other = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $unit = Unit::factory()->for($clinic)->create();
        $type = TicketType::factory()->for($clinic)->create();

        $mine = $this->makeCompletedTicket($clinic->id, $unit->id, $type->id, $attendant->id);
        $theirs = $this->makeCompletedTicket($clinic->id, $unit->id, $type->id, $other->id);

        Livewire::actingAs($attendant)
            ->test(AttendantHistory::class)
            ->assertSee($mine->display_code)
            ->assertDontSee($theirs->display_code);
    }

    public function test_chat_same_clinic_send_and_unread_isolation(): void
    {
        [$clinic, , , $attendant] = $this->seedClinic();
        $peer = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        app(EnsureClinicRolePermissions::class)->handle($clinic);

        $conversation = app(OpenDirectConversation::class)->handle($attendant, $peer);
        app(SendClinicMessage::class)->handle($attendant, $conversation, 'Olá equipe');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $attendant->id,
            'body' => 'Olá equipe',
        ]);

        Livewire::actingAs($peer)
            ->test(AttendantMessages::class)
            ->call('openConversation', $conversation->id)
            ->assertSee('Olá equipe');

        $clinicB = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinicB);
        $outsider = User::factory()->create(['clinic_id' => $clinicB->id, 'role' => UserRole::ATTENDANT]);

        Livewire::actingAs($outsider)
            ->test(AttendantMessages::class)
            ->call('openConversation', $conversation->id)
            ->assertForbidden();
    }

    public function test_chat_rejects_empty_body_and_cross_clinic_peer(): void
    {
        [$clinic, , , $attendant] = $this->seedClinic();
        $peer = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $conversation = app(OpenDirectConversation::class)->handle($attendant, $peer);

        $this->expectException(ValidationException::class);
        app(SendClinicMessage::class)->handle($attendant, $conversation, '   ');
    }

    public function test_profile_updates_name_email_and_password_protects_role(): void
    {
        [, , , $attendant] = $this->seedClinic();
        $originalRole = $attendant->role;
        $originalClinic = $attendant->clinic_id;

        Livewire::actingAs($attendant)
            ->test(AttendantProfile::class)
            ->set('name', 'Maria Atendente')
            ->set('email', $attendant->email)
            ->call('saveIdentity')
            ->assertSet('statusMessage', 'Dados atualizados.');

        $this->assertSame('Maria Atendente', $attendant->fresh()->name);
        $this->assertSame($originalRole, $attendant->fresh()->role);
        $this->assertSame($originalClinic, $attendant->fresh()->clinic_id);

        Livewire::actingAs($attendant)
            ->test(AttendantProfile::class)
            ->set('current_password', 'password')
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('savePassword')
            ->assertSet('statusMessage', 'Senha alterada com sucesso.');

        $this->assertTrue(Hash::check('new-password-123', $attendant->fresh()->password));
    }

    public function test_profile_avatar_upload_and_invalid_file(): void
    {
        Storage::fake('public');
        [, , , $attendant] = $this->seedClinic();

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        Livewire::actingAs($attendant)
            ->test(AttendantProfile::class)
            ->set('avatar', $file)
            ->call('saveAvatar')
            ->assertSet('statusMessage', 'Foto atualizada.');

        $this->assertNotNull($attendant->fresh()->avatar_path);

        Livewire::actingAs($attendant)
            ->test(AttendantProfile::class)
            ->set('avatar', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
            ->call('saveAvatar')
            ->assertHasErrors('avatar');
    }

    public function test_completed_by_is_stamped_on_complete_action(): void
    {
        [$clinic, , , $attendant] = $this->seedClinic();
        $unit = Unit::factory()->for($clinic)->create();
        $type = TicketType::factory()->for($clinic)->create();
        $attendant->units()->attach($unit, ['clinic_id' => $clinic->id]);

        $ticket = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'ticket_type_id' => $type->id,
            'status' => TicketStatus::IN_SERVICE,
            'called_by_user_id' => $attendant->id,
            'started_by_user_id' => $attendant->id,
            'service_started_at' => now()->subMinutes(5),
            'called_at' => now()->subMinutes(6),
        ]);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());

        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
        ]);
        app(ClaimDesk::class)->handle($attendant, $desk);
        $ticket->forceFill(['current_desk_id' => $desk->id])->save();

        app(CompleteTicketService::class)->handle($attendant, $ticket->fresh());

        $this->assertSame($attendant->id, $ticket->fresh()->completed_by_user_id);
        $this->assertSame(TicketStatus::COMPLETED, $ticket->fresh()->status);
    }

    /**
     * @return array{0: Clinic, 1: User, 2: User, 3: User}
     */
    private function seedClinic(): array
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);

        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $supervisor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::SUPERVISOR,
        ]);
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);

        return [$clinic, $admin, $supervisor, $attendant];
    }

    private function makeCompletedTicket(int $clinicId, int $unitId, int $typeId, int $userId): Ticket
    {
        $this->sequence++;

        return Ticket::factory()->create([
            'clinic_id' => $clinicId,
            'unit_id' => $unitId,
            'ticket_type_id' => $typeId,
            'sequence_number' => $this->sequence,
            'status' => TicketStatus::COMPLETED,
            'called_by_user_id' => $userId,
            'started_by_user_id' => $userId,
            'completed_by_user_id' => $userId,
            'called_at' => now()->subMinutes(10),
            'service_started_at' => now()->subMinutes(8),
            'completed_at' => now()->subMinutes(2),
            'queued_at' => now()->subMinutes(15),
            'issued_at' => now()->subMinutes(15),
        ]);
    }
}
