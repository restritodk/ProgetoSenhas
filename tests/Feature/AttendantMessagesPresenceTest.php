<?php

namespace Tests\Feature;

use App\Actions\EnsureClinicRolePermissions;
use App\Actions\OpenDirectConversation;
use App\Actions\SendClinicMessage;
use App\Livewire\AttendantMessages;
use App\Livewire\AttendantPresenceHeartbeat;
use App\Models\Clinic;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Unit;
use App\Models\User;
use App\Services\ClinicMessageInbox;
use App\Services\UserPresence;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AttendantMessagesPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_lists_active_attendants_and_supervisors_same_clinic_only(): void
    {
        [$clinic, $actor, $peer] = $this->seedAttendants();
        $supervisor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::SUPERVISOR,
            'name' => 'Supervisor Ana',
        ]);
        $inactive = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
            'active' => false,
            'name' => 'Inativo Silva',
        ]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
            'name' => 'Admin Local',
        ]);
        $otherClinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($otherClinic);
        $foreign = User::factory()->create([
            'clinic_id' => $otherClinic->id,
            'role' => UserRole::ATTENDANT,
            'name' => 'Outra Clinica',
        ]);

        $peers = Livewire::actingAs($actor)
            ->test(AttendantMessages::class)
            ->instance()
            ->directoryPeers;

        $names = $peers->map(fn (array $row): string => $row['user']->name)->all();

        $this->assertContains($peer->name, $names);
        $this->assertContains($supervisor->name, $names);
        $this->assertNotContains($actor->name, $names);
        $this->assertNotContains($inactive->name, $names);
        $this->assertNotContains($admin->name, $names);
        $this->assertNotContains($foreign->name, $names);
    }

    public function test_admin_message_does_not_inflate_badge_or_conversations(): void
    {
        [$clinic, $actor] = $this->seedAttendants();
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
            'name' => 'Admin Legacy',
        ]);

        // Legacy conversation created before the operational-chat rule (simulate DB history).
        $conversation = new Conversation;
        $conversation->forceFill(['clinic_id' => $clinic->id])->save();
        foreach ([$actor, $admin] as $user) {
            $participant = new ConversationParticipant;
            $participant->forceFill([
                'conversation_id' => $conversation->id,
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'last_read_at' => null,
            ])->save();
        }
        $message = new Message;
        $message->forceFill([
            'conversation_id' => $conversation->id,
            'clinic_id' => $clinic->id,
            'sender_id' => $admin->id,
            'body' => 'Mensagem legada do admin',
        ])->save();

        $this->assertSame(0, app(ClinicMessageInbox::class)->unreadCountFor($actor));

        $conversations = Livewire::actingAs($actor)
            ->test(AttendantMessages::class)
            ->instance()
            ->conversations;

        $this->assertTrue($conversations->isEmpty());
        $this->assertFalse(
            Livewire::actingAs($actor)
                ->test(AttendantMessages::class)
                ->instance()
                ->directoryPeers
                ->contains(fn (array $row): bool => $row['user']->id === $admin->id)
        );
    }

    public function test_administrator_cannot_open_or_message_operational_chat(): void
    {
        [$clinic, $actor] = $this->seedAttendants();
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(AttendantMessages::class)
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('attendant.messages'))
            ->assertForbidden();

        $this->expectException(ValidationException::class);
        app(OpenDirectConversation::class)->handle($actor, $admin);
    }

    public function test_supervisor_and_attendant_can_chat_across_units_same_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $unitA = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $unitB = Unit::factory()->for($clinic)->create(['name' => 'Unidade Principal']);

        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
            'name' => 'Atendente João',
        ]);
        $attendant->units()->attach($unitA->id, ['clinic_id' => $clinic->id]);

        $supervisor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::SUPERVISOR,
            'name' => 'Supervisor Ana',
        ]);
        $supervisor->units()->attach($unitB->id, ['clinic_id' => $clinic->id]);

        $conversation = app(OpenDirectConversation::class)->handle($supervisor, $attendant);
        app(SendClinicMessage::class)->handle($supervisor, $conversation, 'Compareça à recepção.');

        $this->assertSame(1, app(ClinicMessageInbox::class)->unreadCountFor($attendant));

        Livewire::actingAs($attendant)
            ->test(AttendantMessages::class)
            ->assertSee('Supervisor Ana')
            ->call('openConversation', $conversation->id)
            ->assertSee('Compareça à recepção.')
            ->set('body', 'A caminho.')
            ->call('sendMessage')
            ->assertSee('A caminho.');

        $this->assertSame(0, app(ClinicMessageInbox::class)->unreadCountFor($attendant->fresh()));
        $this->assertSame(1, app(ClinicMessageInbox::class)->unreadCountFor($supervisor->fresh()));
    }

    public function test_two_supervisors_can_chat(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $ana = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::SUPERVISOR,
            'name' => 'Ana',
        ]);
        $bruno = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::SUPERVISOR,
            'name' => 'Bruno',
        ]);

        $conversation = app(OpenDirectConversation::class)->handle($ana, $bruno);
        app(SendClinicMessage::class)->handle($ana, $conversation, 'Alinhamento de plantão');

        Livewire::actingAs($bruno)
            ->test(AttendantMessages::class)
            ->call('openConversation', $conversation->id)
            ->assertSee('Alinhamento de plantão');
    }

    public function test_cross_clinic_isolation_and_self_chat_blocked(): void
    {
        [, $actor, $peer] = $this->seedAttendants();
        $otherClinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($otherClinic);
        $outsider = User::factory()->create([
            'clinic_id' => $otherClinic->id,
            'role' => UserRole::ATTENDANT,
        ]);

        try {
            app(OpenDirectConversation::class)->handle($actor, $outsider);
            $this->fail('Cross-clinic chat must be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            app(OpenDirectConversation::class)->handle($actor, $actor);
            $this->fail('Self-chat must be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $conversation = app(OpenDirectConversation::class)->handle($actor, $peer);

        // Outsider may use chat in their own clinic, but must not open another clinic's conversation.
        Livewire::actingAs($outsider)
            ->test(AttendantMessages::class)
            ->call('openConversation', $conversation->id)
            ->assertForbidden();

        Livewire::actingAs($peer)
            ->test(AttendantMessages::class)
            ->call('openConversation', $conversation->id)
            ->assertOk();
    }

    public function test_unread_totals_match_conversation_badges(): void
    {
        [$clinic, $actor, $peer] = $this->seedAttendants();
        $supervisor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::SUPERVISOR,
            'name' => 'Supervisor Ana',
        ]);

        $withPeer = app(OpenDirectConversation::class)->handle($actor, $peer);
        $withSupervisor = app(OpenDirectConversation::class)->handle($actor, $supervisor);

        app(SendClinicMessage::class)->handle($peer, $withPeer, 'msg 1');
        app(SendClinicMessage::class)->handle($peer, $withPeer, 'msg 2');
        app(SendClinicMessage::class)->handle($supervisor, $withSupervisor, 'sup 1');
        app(SendClinicMessage::class)->handle($supervisor, $withSupervisor, 'sup 2');
        app(SendClinicMessage::class)->handle($supervisor, $withSupervisor, 'sup 3');

        $component = Livewire::actingAs($actor)->test(AttendantMessages::class)->instance();
        $byConversation = $component->conversations->sum(fn (array $row): int => $row['unread']);
        $global = app(ClinicMessageInbox::class)->unreadCountFor($actor);

        $this->assertSame(5, $global);
        $this->assertSame($global, $byConversation);
    }

    public function test_presence_online_offline_via_heartbeat_and_expiry(): void
    {
        [$clinic, $actor, $peer] = $this->seedAttendants();
        $presence = app(UserPresence::class);

        $this->assertFalse($presence->isOnline($peer));

        Livewire::actingAs($peer)
            ->test(AttendantPresenceHeartbeat::class)
            ->call('beat');

        $this->assertTrue($presence->isOnline($peer->fresh()));
        $this->assertSame('Online', $presence->statusLabel($peer->fresh()));

        $peer->forceFill([
            'last_seen_at' => now()->subSeconds(UserPresence::ONLINE_WINDOW_SECONDS + 5),
        ])->save();

        $this->assertFalse($presence->isOnline($peer->fresh()));
        $this->assertSame('Offline', $presence->statusLabel($peer->fresh()));

        Livewire::actingAs($actor)
            ->test(AttendantMessages::class)
            ->assertSee($peer->name)
            ->assertSee('Offline');
    }

    public function test_click_opens_idempotent_conversation_and_exchanges_messages(): void
    {
        [, $actor, $peer] = $this->seedAttendants();

        Livewire::actingAs($actor)
            ->test(AttendantMessages::class)
            ->call('startWithPeer', $peer->id)
            ->assertSet('activeConversationId', fn ($id) => $id !== null)
            ->assertSee($peer->name);

        $this->assertSame(1, Conversation::query()->count());

        $conversationId = (int) Livewire::actingAs($actor)
            ->test(AttendantMessages::class)
            ->call('startWithPeer', $peer->id)
            ->get('activeConversationId');

        Livewire::actingAs($actor)
            ->test(AttendantMessages::class)
            ->call('startWithPeer', $peer->id);

        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame($conversationId, (int) Conversation::query()->value('id'));

        Livewire::actingAs($actor)
            ->test(AttendantMessages::class)
            ->call('openConversation', $conversationId)
            ->set('body', 'Pode assumir a mesa...')
            ->call('sendMessage')
            ->assertSee('Pode assumir a mesa...');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $actor->id,
            'body' => 'Pode assumir a mesa...',
        ]);

        Livewire::actingAs($peer)
            ->test(AttendantMessages::class)
            ->call('openConversation', $conversationId)
            ->assertSee('Pode assumir a mesa...')
            ->set('body', 'Sim, assumo.')
            ->call('sendMessage');

        Livewire::actingAs($actor)
            ->test(AttendantMessages::class)
            ->call('openConversation', $conversationId)
            ->assertSee('Sim, assumo.');
    }

    public function test_unread_mark_read_tenant_isolation_and_security(): void
    {
        [, $actor, $peer] = $this->seedAttendants();
        $conversation = app(OpenDirectConversation::class)->handle($actor, $peer);
        app(SendClinicMessage::class)->handle($peer, $conversation, 'Nova mensagem');

        $this->assertSame(1, app(ClinicMessageInbox::class)->unreadCountFor($actor));

        Livewire::actingAs($actor)
            ->test(AttendantMessages::class)
            ->call('openConversation', $conversation->id);

        $this->assertSame(0, app(ClinicMessageInbox::class)->unreadCountFor($actor->fresh()));

        $otherClinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($otherClinic);
        $outsider = User::factory()->create([
            'clinic_id' => $otherClinic->id,
            'role' => UserRole::ATTENDANT,
        ]);

        Livewire::actingAs($outsider)
            ->test(AttendantMessages::class)
            ->call('openConversation', $conversation->id)
            ->assertForbidden();

        $this->expectException(ValidationException::class);
        app(SendClinicMessage::class)->handle($actor, $conversation, '   ');
    }

    public function test_html_is_stored_as_text_and_body_has_size_limit(): void
    {
        [, $actor, $peer] = $this->seedAttendants();
        $conversation = app(OpenDirectConversation::class)->handle($actor, $peer);

        $html = '<script>alert(1)</script>';
        $message = app(SendClinicMessage::class)->handle($actor, $conversation, $html);
        $this->assertSame($html, $message->body);

        $this->assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            Livewire::actingAs($peer)
                ->test(AttendantMessages::class)
                ->call('openConversation', $conversation->id)
                ->html()
        );

        $this->expectException(ValidationException::class);
        app(SendClinicMessage::class)->handle(
            $actor,
            $conversation,
            str_repeat('a', SendClinicMessage::MAX_BODY_LENGTH + 1)
        );
    }

    public function test_logout_marks_presence_offline(): void
    {
        [, $actor] = $this->seedAttendants();
        app(UserPresence::class)->touch($actor);
        $this->assertTrue(app(UserPresence::class)->isOnline($actor->fresh()));

        $this->actingAs($actor);
        $this->assertLoggedOutFeedback($this->post(route('logout')));

        $this->assertNull($actor->fresh()->last_seen_at);
        $this->assertFalse(app(UserPresence::class)->isOnline($actor->fresh()));
    }

    public function test_polling_refresh_does_not_clear_composer_body(): void
    {
        [, $actor, $peer] = $this->seedAttendants();
        $conversation = app(OpenDirectConversation::class)->handle($actor, $peer);

        Livewire::actingAs($actor)
            ->test(AttendantMessages::class)
            ->call('openConversation', $conversation->id)
            ->set('body', 'Pode assumir a mesa...')
            ->call('refreshInbox')
            ->assertSet('body', 'Pode assumir a mesa...');
    }

    public function test_inactive_peer_history_visible_but_cannot_send(): void
    {
        [, $actor, $peer] = $this->seedAttendants();
        $conversation = app(OpenDirectConversation::class)->handle($actor, $peer);
        app(SendClinicMessage::class)->handle($peer, $conversation, 'Antes de sair');

        $peer->forceFill(['active' => false])->save();

        Livewire::actingAs($actor)
            ->test(AttendantMessages::class)
            ->assertSee($peer->name)
            ->assertSee('Usuário inativo')
            ->call('openConversation', $conversation->id)
            ->assertSee('Antes de sair')
            ->assertSee('Não é possível enviar novas mensagens');

        $this->expectException(ValidationException::class);
        app(SendClinicMessage::class)->handle($actor, $conversation, 'Ainda aí?');
    }

    /**
     * @return array{0: Clinic, 1: User, 2: User}
     */
    private function seedAttendants(): array
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);

        $actor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
            'name' => 'Maria Silva',
        ]);
        $peer = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
            'name' => 'João Santos',
        ]);

        return [$clinic, $actor, $peer];
    }
}
