<?php

namespace Tests\Feature;

use App\Actions\SyncUnitTicketTypes;
use App\Livewire\PublicKiosk;
use App\Models\Clinic;
use App\Models\Kiosk;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicKioskTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_opens_kiosk_and_invalid_token_is_generic(): void
    {
        [$kiosk] = $this->readyKiosk();

        $this->get(route('kiosk.panel', $kiosk->public_token))
            ->assertOk()
            ->assertSee('Retire sua senha')
            ->assertSee('Atendimento Preferencial')
            ->assertSee('Atendimento Normal')
            ->assertDontSee('Emergencial');

        $this->get(route('kiosk.panel', str_repeat('a', 64)))
            ->assertNotFound()
            ->assertDontSee($kiosk->clinic->name)
            ->assertDontSee($kiosk->unit->name)
            ->assertDontSee('clinic_id')
            ->assertDontSee('Totem Centro');
    }

    public function test_offered_types_respect_position_and_display_name(): void
    {
        [$kiosk] = $this->readyKiosk();

        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->assertSeeInOrder([
                'Atendimento Preferencial',
                'Atendimento Normal',
            ]);
    }

    public function test_inactive_kiosk_clinic_or_unit_shows_unavailable(): void
    {
        [$kiosk, $clinic, $unit] = $this->readyKiosk();

        $kiosk->forceFill(['active' => false])->save();
        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->assertSet('screen', 'unavailable')
            ->assertSee('Totem temporariamente indisponível');

        $kiosk->forceFill(['active' => true])->save();
        $unit->forceFill(['active' => false])->save();
        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->fresh()->public_token])
            ->assertSet('screen', 'unavailable');

        $unit->forceFill(['active' => true])->save();
        $clinic->forceFill(['active' => false])->save();
        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->fresh()->public_token])
            ->assertSet('screen', 'unavailable');
    }

    public function test_inactive_global_ticket_type_does_not_appear(): void
    {
        [$kiosk, $clinic, $unit, $admin, $types] = $this->readyKiosk();
        $types['preferential']->forceFill(['active' => false])->save();

        $this->get(route('kiosk.panel', $kiosk->public_token))
            ->assertOk()
            ->assertDontSee('Atendimento Preferencial')
            ->assertSee('Atendimento Normal');
    }

    public function test_emergency_appears_only_when_unit_offer_is_active(): void
    {
        [$kiosk, $clinic, $unit, $admin, $types] = $this->readyKiosk();

        $this->get(route('kiosk.panel', $kiosk->public_token))
            ->assertDontSee('Emergencial');

        app(SyncUnitTicketTypes::class)->handle($admin, $unit, [
            [
                'ticket_type_id' => $types['preferential']->id,
                'active' => true,
                'display_name' => 'Atendimento Preferencial',
                'position' => 10,
            ],
            [
                'ticket_type_id' => $types['normal']->id,
                'active' => true,
                'display_name' => 'Atendimento Normal',
                'position' => 20,
            ],
            [
                'ticket_type_id' => $types['emergency']->id,
                'active' => true,
                'display_name' => 'Atendimento Emergencial',
                'position' => 5,
            ],
        ]);

        $this->get(route('kiosk.panel', $kiosk->public_token))
            ->assertOk()
            ->assertSee('Atendimento Emergencial')
            ->assertSee('Atendimento Preferencial')
            ->assertSee('Atendimento Normal')
            ->assertSee('kiosk-cards--three', false);
    }

    public function test_custom_ticket_type_appears_without_hardcoded_prefix(): void
    {
        [$kiosk, $clinic, $unit, $admin, $types] = $this->readyKiosk();
        $vaccination = $this->type($clinic, 'Vacinação', 'VAC', 15);

        app(SyncUnitTicketTypes::class)->handle($admin, $unit, [
            [
                'ticket_type_id' => $vaccination->id,
                'active' => true,
                'display_name' => 'Vacinação',
                'position' => 1,
            ],
            [
                'ticket_type_id' => $types['preferential']->id,
                'active' => false,
                'display_name' => null,
                'position' => 10,
            ],
            [
                'ticket_type_id' => $types['normal']->id,
                'active' => false,
                'display_name' => null,
                'position' => 20,
            ],
            [
                'ticket_type_id' => $types['emergency']->id,
                'active' => false,
                'display_name' => null,
                'position' => 30,
            ],
        ]);

        $this->get(route('kiosk.panel', $kiosk->public_token))
            ->assertOk()
            ->assertSee('Vacinação')
            ->assertSee('Toque para retirar sua senha de atendimento')
            ->assertDontSee('Atendimento Preferencial')
            ->assertSee('kiosk-cards--one', false);
    }

    public function test_success_screen_and_finish_return_home(): void
    {
        [$kiosk, , , , $types] = $this->readyKiosk();

        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $types['normal']->id)
            ->assertSet('screen', 'result')
            ->assertSee('Senha emitida')
            ->assertSee('N001')
            ->assertSee('Atendimento Normal')
            ->call('finish')
            ->assertSet('screen', 'home')
            ->assertSet('issuedDisplayCode', '')
            ->assertSet('errorMessage', '');
    }

    public function test_clear_error_returns_to_home_actions(): void
    {
        [$kiosk] = $this->readyKiosk();

        Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->set('errorMessage', 'Não foi possível emitir sua senha. Tente novamente ou procure a recepção.')
            ->assertSee('Tentar novamente')
            ->call('clearError')
            ->assertSet('errorMessage', '')
            ->assertSee('Retire sua senha');
    }

    public function test_refresh_availability_reflects_offer_changes_in_realtime(): void
    {
        [$kiosk, $clinic, $unit, $admin, $types] = $this->readyKiosk();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->assertSee('Atendimento Normal')
            ->assertDontSee('Atendimento Emergencial');

        app(SyncUnitTicketTypes::class)->handle($admin, $unit, [
            [
                'ticket_type_id' => $types['preferential']->id,
                'active' => true,
                'display_name' => 'Atendimento Preferencial',
                'position' => 10,
            ],
            [
                'ticket_type_id' => $types['normal']->id,
                'active' => true,
                'display_name' => 'Atendimento Normal',
                'position' => 20,
            ],
            [
                'ticket_type_id' => $types['emergency']->id,
                'active' => true,
                'display_name' => 'Atendimento Emergencial',
                'position' => 5,
            ],
        ]);

        $component->call('refreshAvailability')
            ->assertSee('Atendimento Emergencial')
            ->assertSee('Atendimento Normal');

        $types['emergency']->forceFill(['active' => false])->save();

        $component->call('refreshAvailability')
            ->assertDontSee('Atendimento Emergencial')
            ->assertSee('Atendimento Normal');
    }

    public function test_issue_rejects_type_that_became_ineligible_server_side(): void
    {
        [$kiosk, , $unit, $admin, $types] = $this->readyKiosk();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->assertSee('Atendimento Normal');

        app(SyncUnitTicketTypes::class)->handle($admin, $unit, [
            [
                'ticket_type_id' => $types['preferential']->id,
                'active' => true,
                'display_name' => 'Atendimento Preferencial',
                'position' => 10,
            ],
            [
                'ticket_type_id' => $types['normal']->id,
                'active' => false,
                'display_name' => 'Atendimento Normal',
                'position' => 20,
            ],
            [
                'ticket_type_id' => $types['emergency']->id,
                'active' => false,
                'display_name' => null,
                'position' => 30,
            ],
        ]);

        $component->call('issue', $types['normal']->id)
            ->assertSet('screen', 'home')
            ->assertSet('issuedTicketId', null)
            ->assertSee('Esta opção de atendimento acabou de ficar indisponível');

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_polling_does_not_reset_result_screen_or_timer_state(): void
    {
        [$kiosk, , , , $types] = $this->readyKiosk();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token])
            ->call('issue', $types['normal']->id)
            ->assertSet('screen', 'result')
            ->assertSet('issuedDisplayCode', 'N001');

        $component->call('refreshAvailability')
            ->assertSet('screen', 'result')
            ->assertSet('issuedDisplayCode', 'N001')
            ->assertSet('issuedTicketId', Ticket::query()->value('id'));

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_premium_layout_markers_are_present(): void
    {
        [$kiosk] = $this->readyKiosk();

        $this->get(route('kiosk.panel', $kiosk->public_token))
            ->assertOk()
            ->assertSee('kiosk-shell', false)
            ->assertSee('kiosk-card', false)
            ->assertSee('kiosk-footer', false)
            ->assertSee('Humanização em cada atendimento')
            ->assertSee('Retire sua senha')
            ->assertSee('É rápido e fácil');
    }

    /**
     * @return array{0: Kiosk, 1: Clinic, 2: Unit, 3: User, 4: array{normal: TicketType, preferential: TicketType, emergency: TicketType}}
     */
    private function readyKiosk(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Centro']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $normal = $this->type($clinic, 'Normal', 'N', 10);
        $preferential = $this->type($clinic, 'Preferencial', 'P', 20);
        $emergency = $this->type($clinic, 'Emergencial', 'E', 30);

        app(SyncUnitTicketTypes::class)->handle($admin, $unit, [
            [
                'ticket_type_id' => $preferential->id,
                'active' => true,
                'display_name' => 'Atendimento Preferencial',
                'position' => 10,
            ],
            [
                'ticket_type_id' => $normal->id,
                'active' => true,
                'display_name' => 'Atendimento Normal',
                'position' => 20,
            ],
            [
                'ticket_type_id' => $emergency->id,
                'active' => false,
                'display_name' => null,
                'position' => 30,
            ],
        ]);

        $kiosk = Kiosk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Totem Centro',
            'code' => 'TC',
            'active' => true,
        ]);

        return [$kiosk, $clinic, $unit, $admin, [
            'normal' => $normal,
            'preferential' => $preferential,
            'emergency' => $emergency,
        ]];
    }

    private function type(Clinic $clinic, string $name, string $prefix, int $priority): TicketType
    {
        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'prefix' => $prefix,
            'priority' => $priority,
            'active' => true,
        ])->save();

        return $type->refresh();
    }
}
