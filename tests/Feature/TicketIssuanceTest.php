<?php

namespace Tests\Feature;

use App\Actions\IssueTicket;
use App\Livewire\TicketIssuer;
use App\Models\Clinic;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\TicketStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketIssuanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_access_issue_screen(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->get(route('tickets.issue'))
            ->assertOk()
            ->assertSee('Emitir senha');

        $this->assertTrue($admin->can('create', Ticket::class));
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrator_cannot_issue_tickets(UserRole $role): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => $role,
        ]);

        $this->actingAs($user)->get(route('tickets.issue'))->assertForbidden();

        Livewire::actingAs($user)
            ->test(TicketIssuer::class)
            ->assertForbidden();

        $this->assertFalse($user->can('create', Ticket::class));
    }

    public function test_issues_sequential_numbers_per_type_and_unit_with_display_code(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unitA = Unit::factory()->for($clinic)->create(['name' => 'Unidade A']);
        $unitB = Unit::factory()->for($clinic)->create(['name' => 'Unidade B']);
        $normal = $this->ticketType($clinic, 'Normal', 'N', 10);
        $preferential = $this->ticketType($clinic, 'Preferencial', 'P', 20);
        $emergency = $this->ticketType($clinic, 'Emergencial', 'E', 30);

        $this->actingAs($admin);
        $issuer = app(IssueTicket::class);

        $n1 = $issuer->handle($admin, $unitA->id, $normal->id);
        $n2 = $issuer->handle($admin, $unitA->id, $normal->id);
        $p1 = $issuer->handle($admin, $unitA->id, $preferential->id);
        $e1 = $issuer->handle($admin, $unitA->id, $emergency->id);
        $n1UnitB = $issuer->handle($admin, $unitB->id, $normal->id);

        $this->assertSame(TicketStatus::WAITING, $n1->status);
        $this->assertSame(1, $n1->sequence_number);
        $this->assertSame('N001', $n1->display_code);
        $this->assertSame(2, $n2->sequence_number);
        $this->assertSame('N002', $n2->display_code);
        $this->assertSame('P001', $p1->display_code);
        $this->assertSame('E001', $e1->display_code);
        $this->assertSame('N001', $n1UnitB->display_code);
        $this->assertSame($unitB->id, $n1UnitB->unit_id);
        $this->assertSame($clinic->id, $n1->clinic_id);
    }

    public function test_sequence_resets_on_next_operational_day(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unit = Unit::factory()->for($clinic)->create();
        $normal = $this->ticketType($clinic, 'Normal', 'N', 10);

        $dayOne = CarbonImmutable::parse('2026-09-23 10:00:00', config('app.timezone'));
        $dayTwo = CarbonImmutable::parse('2026-09-24 08:00:00', config('app.timezone'));

        $this->actingAs($admin);
        $issuer = app(IssueTicket::class);

        $first = $issuer->handle($admin, $unit->id, $normal->id, $dayOne);
        $second = $issuer->handle($admin, $unit->id, $normal->id, $dayOne);
        $nextDay = $issuer->handle($admin, $unit->id, $normal->id, $dayTwo);

        $this->assertSame('2026-09-23', $first->sequence_date->toDateString());
        $this->assertSame(2, $second->sequence_number);
        $this->assertSame('2026-09-24', $nextDay->sequence_date->toDateString());
        $this->assertSame(1, $nextDay->sequence_number);
        $this->assertSame('N001', $nextDay->display_code);
    }

    public function test_rejects_inactive_unit_and_ticket_type(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $inactiveUnit = Unit::factory()->for($clinic)->create(['active' => false]);
        $activeUnit = Unit::factory()->for($clinic)->create(['active' => true]);
        $inactiveType = $this->ticketType($clinic, 'Inativo', 'X', 10, active: false);
        $activeType = $this->ticketType($clinic, 'Ativo', 'A', 10);

        $this->actingAs($admin);
        $issuer = app(IssueTicket::class);

        try {
            $issuer->handle($admin, $inactiveUnit->id, $activeType->id);
            $this->fail('Expected ValidationException for inactive unit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unitId', $exception->errors());
        }

        try {
            $issuer->handle($admin, $activeUnit->id, $inactiveType->id);
            $this->fail('Expected ValidationException for inactive ticket type.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticketTypeId', $exception->errors());
        }
    }

    public function test_rejects_cross_tenant_unit_and_ticket_type(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $typeA = $this->ticketType($clinicA, 'Normal', 'N', 10);
        $typeB = $this->ticketType($clinicB, 'Normal', 'N', 10);

        $this->actingAs($admin);
        $issuer = app(IssueTicket::class);

        try {
            $issuer->handle($admin, $unitB->id, $typeA->id);
            $this->fail('Expected ValidationException for foreign unit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unitId', $exception->errors());
        }

        try {
            $issuer->handle($admin, $unitA->id, $typeB->id);
            $this->fail('Expected ValidationException for foreign ticket type.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticketTypeId', $exception->errors());
        }

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_livewire_issues_ticket_and_shows_queue(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unit = Unit::factory()->for($clinic)->create();
        $type = $this->ticketType($clinic, 'Preferencial', 'P', 20);

        Livewire::actingAs($admin)
            ->test(TicketIssuer::class)
            ->set('unitId', $unit->id)
            ->set('ticketTypeId', $type->id)
            ->call('issue')
            ->assertHasNoErrors()
            ->assertSee('Senha emitida com sucesso.')
            ->assertSee('P001')
            ->assertSee('Preferencial');

        $this->assertDatabaseHas('tickets', [
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'ticket_type_id' => $type->id,
            'sequence_number' => 1,
            'status' => TicketStatus::WAITING->value,
        ]);
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function nonAdministratorRoles(): array
    {
        return [
            'supervisor' => [UserRole::SUPERVISOR],
            'attendant' => [UserRole::ATTENDANT],
        ];
    }

    private function administrator(?Clinic $clinic = null): User
    {
        $clinic ??= Clinic::factory()->create();

        return User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
    }

    private function ticketType(
        Clinic $clinic,
        string $name,
        string $prefix,
        int $priority,
        bool $active = true,
    ): TicketType {
        $ticketType = new TicketType;
        $ticketType->forceFill([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'prefix' => $prefix,
            'priority' => $priority,
            'active' => $active,
        ])->save();

        return $ticketType->refresh();
    }
}
