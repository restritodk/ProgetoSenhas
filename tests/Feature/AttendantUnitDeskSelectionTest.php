<?php

namespace Tests\Feature;

use App\Actions\ClaimDesk;
use App\Actions\EnsureClinicRolePermissions;
use App\Livewire\AttendantPanel;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AttendantUnitDeskSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_authorized_unit_is_auto_selected_on_mount(): void
    {
        [$attendant, $unit] = $this->attendantWithUnits(1);

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->assertSee('Selecione sua mesa / guichê')
            ->assertSee($unit->name);

        $this->assertSame($unit->id, app(OperationalContext::class)->activeUnit($attendant, session())?->id);
    }

    public function test_multiple_units_require_manual_selection(): void
    {
        [$attendant, $unitA, $unitB] = $this->attendantWithUnits(2);

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->assertSee('Selecione a unidade')
            ->assertSee($unitA->name)
            ->assertSee($unitB->name)
            ->assertDontSee('Selecione sua mesa / guichê');
    }

    public function test_only_authorized_units_of_same_clinic_are_listed(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $authorized = Unit::factory()->for($clinic)->create(['name' => 'Unidade autorizada']);
        $unauthorized = Unit::factory()->for($clinic)->create(['name' => 'Unidade não autorizada']);
        $foreignClinic = Clinic::factory()->create();
        $foreignUnit = Unit::factory()->for($foreignClinic)->create(['name' => 'Unidade estrangeira']);

        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendant->units()->detach();
        $attendant->units()->attach($authorized->id, ['clinic_id' => $clinic->id]);

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->assertSee('Unidade autorizada')
            ->assertDontSee('Unidade não autorizada')
            ->assertDontSee('Unidade estrangeira');

        $this->assertFalse(
            app(OperationalContext::class)->setActiveUnit($attendant, $unauthorized, session()),
        );
        $this->assertFalse(
            app(OperationalContext::class)->setActiveUnit($attendant, $foreignUnit, session()),
        );
    }

    public function test_after_unit_selection_only_desks_of_that_unit_appear(): void
    {
        [$attendant, $unitA, $unitB] = $this->attendantWithUnits(2);
        $deskA = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unitA->id,
            'name' => 'Mesa A1',
            'code' => 'A01',
        ]);
        $deskB = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unitB->id,
            'name' => 'Mesa B1',
            'code' => 'B01',
        ]);

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->call('selectUnit', $unitA->id)
            ->assertSee('Mesa A1')
            ->assertDontSee('Mesa B1')
            ->assertSee('Código A01');

        $this->assertSame($unitA->id, app(OperationalContext::class)->activeUnit($attendant, session())?->id);
        $this->assertNull(app(OperationalContext::class)->activeDesk($attendant, session()));

        // Desk from other unit cannot be claimed while Unit A is active.
        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->call('selectUnit', $unitA->id)
            ->call('selectDesk', $deskB->id)
            ->assertSet('errorMessage', 'A mesa selecionada não pertence à unidade atual ou está indisponível.');
    }

    public function test_inactive_desk_cannot_be_selected(): void
    {
        [$attendant, $unit] = $this->attendantWithUnits(1);
        $inactive = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unit->id,
            'active' => false,
            'name' => 'Mesa Inativa',
            'code' => 'I01',
        ]);
        Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Mesa Ativa',
            'code' => 'A01',
        ]);

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->assertSee('Mesa Ativa')
            ->assertDontSee('Mesa Inativa')
            ->call('selectDesk', $inactive->id)
            ->assertSet('errorMessage', 'A mesa selecionada não pertence à unidade atual ou está indisponível.');
    }

    public function test_occupied_desk_is_not_selectable_and_claim_is_denied(): void
    {
        [$attendant, $unit] = $this->attendantWithUnits(1);
        $other = User::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'role' => UserRole::ATTENDANT,
        ]);
        $other->units()->detach();
        $other->units()->attach($unit->id, ['clinic_id' => $attendant->clinic_id]);

        $desk = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Mesa Ocupada',
            'code' => 'O01',
        ]);
        $free = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unit->id,
            'name' => 'Mesa Livre',
            'code' => 'L01',
        ]);

        $this->actingAs($other);
        app(OperationalContext::class)->setActiveUnit($other, $unit, session());
        app(ClaimDesk::class)->handle($other, $desk);

        // Fresh session for the first attendant.
        session()->flush();
        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->assertSee('Mesa Ocupada')
            ->assertSee('Em uso')
            ->assertSee('Mesa Livre')
            ->assertSee('Disponível')
            ->call('selectDesk', $desk->id)
            ->assertSee('Esta mesa acabou de ser ocupada por outro atendente');
    }

    public function test_changing_unit_clears_previous_desk_assignment(): void
    {
        [$attendant, $unitA, $unitB] = $this->attendantWithUnits(2);
        $deskA = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unitA->id,
            'name' => 'Mesa A',
            'code' => 'A01',
        ]);
        Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $unitB->id,
            'name' => 'Mesa B',
            'code' => 'B01',
        ]);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unitA, session());
        app(ClaimDesk::class)->handle($attendant, $deskA);
        $this->assertSame($deskA->id, app(OperationalContext::class)->activeDesk($attendant, session())?->id);

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->call('selectUnit', $unitB->id)
            ->assertSee('Mesa B')
            ->assertDontSee('Mesa A');

        $this->assertSame($unitB->id, app(OperationalContext::class)->activeUnit($attendant, session())?->id);
        $this->assertNull(app(OperationalContext::class)->activeDesk($attendant, session()));
        $this->assertDatabaseMissing('desk_assignments', [
            'desk_id' => $deskA->id,
            'user_id' => $attendant->id,
        ]);
    }

    public function test_unit_without_desks_shows_empty_state_and_allows_back(): void
    {
        [$attendant, $unit] = $this->attendantWithUnits(1);

        Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->assertSee('Esta unidade ainda não possui mesas/guichês ativos')
            ->assertSee('Escolher outra unidade')
            ->call('clearUnit')
            ->assertSee('Selecione a unidade');
    }

    public function test_claim_desk_rejects_cross_unit_and_cross_tenant(): void
    {
        [$attendant, $unit] = $this->attendantWithUnits(1);
        $otherUnit = Unit::factory()->for($attendant->clinic)->create();
        $foreignClinic = Clinic::factory()->create();
        $foreignUnit = Unit::factory()->for($foreignClinic)->create();
        $foreignDesk = Desk::factory()->create([
            'clinic_id' => $foreignClinic->id,
            'unit_id' => $foreignUnit->id,
        ]);
        $otherDesk = Desk::factory()->create([
            'clinic_id' => $attendant->clinic_id,
            'unit_id' => $otherUnit->id,
        ]);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());

        $this->expectException(ValidationException::class);
        app(ClaimDesk::class)->handle($attendant, $otherDesk);
    }

    /**
     * @return array{0: User, 1: Unit}|array{0: User, 1: Unit, 2: Unit}
     */
    private function attendantWithUnits(int $count): array
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);

        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendant->units()->detach();

        $units = [];
        for ($i = 1; $i <= $count; $i++) {
            $unit = Unit::factory()->for($clinic)->create([
                'name' => 'Unidade '.$i,
                'slug' => 'unidade-'.$i.'-'.uniqid(),
            ]);
            $attendant->units()->attach($unit->id, ['clinic_id' => $clinic->id]);
            $units[] = $unit;
        }

        return [$attendant, ...$units];
    }
}
