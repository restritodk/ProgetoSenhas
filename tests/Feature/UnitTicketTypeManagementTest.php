<?php

namespace Tests\Feature;

use App\Actions\SyncUnitTicketTypes;
use App\Livewire\UnitTicketTypesManager;
use App\Models\Clinic;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitTicketType;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class UnitTicketTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_associates_ticket_types_for_own_unit(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unit = Unit::factory()->for($clinic)->create();
        $normal = $this->type($clinic, 'Normal', 'N', 10);
        $preferential = $this->type($clinic, 'Preferencial', 'P', 20);

        $this->actingAs($admin)
            ->get(route('unit-ticket-types.index'))
            ->assertOk()
            ->assertSee('Tipos de senha por unidade');

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
                'display_name' => null,
                'position' => 20,
            ],
        ]);

        $this->assertDatabaseHas('unit_ticket_types', [
            'unit_id' => $unit->id,
            'ticket_type_id' => $preferential->id,
            'active' => true,
            'display_name' => 'Atendimento Preferencial',
            'position' => 10,
        ]);
        $this->assertDatabaseHas('unit_ticket_types', [
            'unit_id' => $unit->id,
            'ticket_type_id' => $normal->id,
            'display_name' => null,
            'position' => 20,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitTicketTypesManager::class)
            ->set('unitId', $unit->id)
            ->assertSee('Preferencial')
            ->assertSee('Normal');
    }

    public function test_foreign_ticket_type_and_cross_tenant_unit_are_rejected(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $unitA = Unit::factory()->for($clinicA)->create();
        $foreignType = $this->type($clinicB, 'Alien', 'X', 10);

        $this->expectException(ValidationException::class);
        app(SyncUnitTicketTypes::class)->handle($admin, $unitA, [
            [
                'ticket_type_id' => $foreignType->id,
                'active' => true,
                'display_name' => null,
                'position' => 1,
            ],
        ]);
    }

    public function test_deactivate_and_public_label_fallback(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unit = Unit::factory()->for($clinic)->create();
        $type = $this->type($clinic, 'Normal', 'N', 10);

        app(SyncUnitTicketTypes::class)->handle($admin, $unit, [
            [
                'ticket_type_id' => $type->id,
                'active' => true,
                'display_name' => 'Atendimento Normal',
                'position' => 5,
            ],
        ]);

        $offer = UnitTicketType::query()->first();
        $this->assertSame('Atendimento Normal', $offer->publicLabel());

        app(SyncUnitTicketTypes::class)->handle($admin, $unit, [
            [
                'ticket_type_id' => $type->id,
                'active' => false,
                'display_name' => null,
                'position' => 5,
            ],
        ]);

        $offer = $offer->fresh()->load('ticketType');
        $this->assertFalse($offer->active);
        $this->assertSame('Normal', $offer->publicLabel());
    }

    public function test_attendant_cannot_manage_unit_ticket_types(): void
    {
        $clinic = Clinic::factory()->create();
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);

        $this->actingAs($attendant)
            ->get(route('unit-ticket-types.index'))
            ->assertForbidden();
    }

    private function administrator(Clinic $clinic): User
    {
        return User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
    }

    private function type(Clinic $clinic, string $name, string $prefix, int $priority, bool $active = true): TicketType
    {
        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'prefix' => $prefix,
            'priority' => $priority,
            'active' => $active,
        ])->save();

        return $type->refresh();
    }
}
