<?php

namespace Tests\Feature;

use App\Actions\CreateTicketType;
use App\Actions\EnsureDefaultTicketTypes;
use App\Livewire\TicketTypesManager;
use App\Models\Clinic;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitTicketType;
use App\Models\User;
use App\UserRole;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TicketTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_accesses_ticket_types_index(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)
            ->get(route('ticket-types.index'))
            ->assertOk()
            ->assertSee('Tipos de Senha')
            ->assertSee('Novo tipo de senha');

        $this->assertTrue($admin->can('viewAny', TicketType::class));
        $this->assertTrue($admin->can('create', TicketType::class));
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrator_cannot_manage_ticket_types(UserRole $role): void
    {
        $clinic = Clinic::factory()->create();
        $user = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => $role,
        ]);

        $response = $this->actingAs($user)->get(route('ticket-types.index'));
        if ($role === UserRole::ATTENDANT) {
            $response->assertRedirect(route('attendant.panel'));
        } else {
            $response->assertForbidden();
        }

        Livewire::actingAs($user)
            ->test(TicketTypesManager::class)
            ->assertForbidden();

        $this->assertFalse($user->can('viewAny', TicketType::class));
        $this->assertFalse($user->can('create', TicketType::class));
    }

    public function test_administrator_creates_ticket_type_with_normalized_prefix_in_own_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('startCreate')
            ->set('name', 'Retorno')
            ->set('prefix', 'r')
            ->set('priority', '15')
            ->set('active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Tipo de senha criado com sucesso.');

        $this->assertDatabaseHas('ticket_types', [
            'clinic_id' => $clinicA->id,
            'name' => 'Retorno',
            'prefix' => 'R',
            'priority' => 15,
            'active' => true,
        ]);
        $this->assertDatabaseMissing('ticket_types', [
            'clinic_id' => $clinicB->id,
            'prefix' => 'R',
        ]);

        $this->actingAs($admin);
        $created = app(CreateTicketType::class)->handle($admin, [
            'name' => 'VIP',
            'prefix' => 'v',
            'priority' => 25,
            'active' => true,
            'clinic_id' => $clinicB->id,
        ]);

        $this->assertSame($clinicA->id, $created->clinic_id);
        $this->assertSame('V', $created->prefix);
    }

    public function test_prefix_is_unique_inside_clinic_and_reusable_in_another_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);

        TicketType::factory()->create([
            'clinic_id' => $clinicA->id,
            'prefix' => 'N',
            'name' => 'Normal A',
            'priority' => 10,
        ]);
        TicketType::factory()->create([
            'clinic_id' => $clinicB->id,
            'prefix' => 'N',
            'name' => 'Normal B',
            'priority' => 10,
        ]);

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('startCreate')
            ->set('name', 'Duplicado')
            ->set('prefix', 'N')
            ->set('priority', '10')
            ->call('save')
            ->assertHasErrors(['prefix']);

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('startCreate')
            ->set('name', 'Gestante')
            ->set('prefix', 'G')
            ->set('priority', '18')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, TicketType::query()->where('clinic_id', $clinicA->id)->where('prefix', 'N')->count());
        $this->assertSame(1, TicketType::query()->where('clinic_id', $clinicB->id)->where('prefix', 'N')->count());
        $this->assertDatabaseHas('ticket_types', [
            'clinic_id' => $clinicA->id,
            'prefix' => 'G',
        ]);
    }

    public function test_administrator_lists_only_own_types_and_cannot_edit_foreign(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = $this->administrator($clinicA);
        $ownType = TicketType::factory()->create([
            'clinic_id' => $clinicA->id,
            'name' => 'Tipo Próprio',
            'prefix' => 'TP',
            'priority' => 12,
        ]);
        $foreignType = TicketType::factory()->create([
            'clinic_id' => $clinicB->id,
            'name' => 'Tipo Externo',
            'prefix' => 'TE',
            'priority' => 12,
        ]);

        $this->actingAs($admin)
            ->get(route('ticket-types.index'))
            ->assertOk()
            ->assertSee('Tipo Próprio')
            ->assertDontSee('Tipo Externo');

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('edit', $foreignType->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('confirmDeactivation', $foreignType->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('confirmActivation', $foreignType->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('confirmDeletion', $foreignType->id)
            ->assertNotFound();

        $this->assertTrue($admin->can('update', $ownType));
        $this->assertFalse($admin->can('update', $foreignType));
    }

    public function test_administrator_edits_and_toggles_own_ticket_type(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $ticketType = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
        ]);

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('edit', $ticketType->id)
            ->set('name', 'Atendimento Normal')
            ->set('prefix', 'AN')
            ->set('priority', '12')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Alterações salvas com sucesso.');

        $this->assertDatabaseHas('ticket_types', [
            'id' => $ticketType->id,
            'clinic_id' => $clinic->id,
            'name' => 'Atendimento Normal',
            'prefix' => 'AN',
            'priority' => 12,
        ]);

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('confirmDeactivation', $ticketType->id)
            ->call('deactivate')
            ->assertSee('Tipo de senha desativado com sucesso.');

        $this->assertFalse($ticketType->fresh()->active);

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('confirmActivation', $ticketType->id)
            ->call('activate')
            ->assertSee('Tipo de senha ativado com sucesso.');

        $this->assertTrue($ticketType->fresh()->active);
    }

    public function test_unused_ticket_type_can_be_deleted_and_used_type_is_blocked(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unused = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Exames',
            'prefix' => 'X',
            'priority' => 12,
        ]);
        $used = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
        ]);
        $unit = Unit::factory()->for($clinic)->create();
        Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'ticket_type_id' => $used->id,
        ]);

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('confirmDeletion', $unused->id)
            ->assertSet('ticketTypePendingDeletionId', $unused->id)
            ->call('delete')
            ->assertSee('Tipo de senha excluído com sucesso.');

        $this->assertDatabaseMissing('ticket_types', ['id' => $unused->id]);

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('confirmDeletion', $used->id)
            ->assertSet('showDeleteBlockedModal', true)
            ->assertSet('ticketTypePendingDeletionId', null);

        $this->assertDatabaseHas('ticket_types', ['id' => $used->id]);
        $this->assertSame(1, Ticket::query()->where('ticket_type_id', $used->id)->count());
    }

    public function test_summary_and_filters_list_active_and_inactive(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        TicketType::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Ativo A', 'prefix' => 'A', 'active' => true]);
        TicketType::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Inativo B', 'prefix' => 'B', 'active' => false]);

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->assertSee('Ativo A')
            ->assertSee('Inativo B')
            ->set('statusFilter', 'active')
            ->assertSee('Ativo A')
            ->assertDontSee('Inativo B')
            ->set('statusFilter', 'inactive')
            ->assertSee('Inativo B')
            ->assertDontSee('Ativo A')
            ->set('statusFilter', '')
            ->set('search', 'Inativo')
            ->assertSee('Inativo B')
            ->assertDontSee('Ativo A');
    }

    public function test_priority_must_be_a_positive_integer(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('startCreate')
            ->set('name', 'Inválido')
            ->set('prefix', 'X')
            ->set('priority', '0')
            ->call('save')
            ->assertHasErrors(['priority']);

        Livewire::actingAs($admin)
            ->test(TicketTypesManager::class)
            ->call('startCreate')
            ->set('name', 'Inválido')
            ->set('prefix', 'X')
            ->set('priority', 'abc')
            ->call('save')
            ->assertHasErrors(['priority']);
    }

    public function test_default_ticket_types_are_created_idempotently(): void
    {
        $clinic = Clinic::factory()->create();

        app(EnsureDefaultTicketTypes::class)->handle($clinic);
        app(EnsureDefaultTicketTypes::class)->handle($clinic);

        $types = TicketType::query()
            ->where('clinic_id', $clinic->id)
            ->orderBy('prefix')
            ->get();

        $this->assertCount(3, $types);
        $this->assertEqualsCanonicalizing(['E', 'N', 'P'], $types->pluck('prefix')->all());
        $this->assertSame(10, $types->firstWhere('prefix', 'N')?->priority);
        $this->assertSame(20, $types->firstWhere('prefix', 'P')?->priority);
        $this->assertSame(30, $types->firstWhere('prefix', 'E')?->priority);
        $this->assertSame('Normal', $types->firstWhere('prefix', 'N')?->name);
        $this->assertSame('Preferencial', $types->firstWhere('prefix', 'P')?->name);
        $this->assertSame('Emergencial', $types->firstWhere('prefix', 'E')?->name);
    }

    public function test_admin_seeder_creates_default_ticket_types_without_duplicating(): void
    {
        $this->app->make(AdminSeeder::class)->run();
        $this->app->make(AdminSeeder::class)->run();

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame(3, TicketType::query()->where('clinic_id', $admin->clinic_id)->count());
        $this->assertDatabaseHas('ticket_types', [
            'clinic_id' => $admin->clinic_id,
            'prefix' => 'N',
            'priority' => 10,
        ]);
        $this->assertDatabaseHas('ticket_types', [
            'clinic_id' => $admin->clinic_id,
            'prefix' => 'P',
            'priority' => 20,
        ]);
        $this->assertDatabaseHas('ticket_types', [
            'clinic_id' => $admin->clinic_id,
            'prefix' => 'E',
            'priority' => 30,
        ]);

        $unit = Unit::query()->where('clinic_id', $admin->clinic_id)->firstOrFail();
        $this->assertSame(3, UnitTicketType::query()
            ->where('clinic_id', $admin->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('active', true)
            ->count());
    }

    public function test_clinic_and_prefix_are_not_mass_assignable_for_tenant_escape(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $ticketType = TicketType::factory()->create([
            'clinic_id' => $clinicA->id,
            'prefix' => 'N',
            'priority' => 10,
        ]);

        $ticketType->fill([
            'name' => 'Alterado',
            'clinic_id' => $clinicB->id,
            'prefix' => 'Z',
            'priority' => 99,
            'active' => false,
        ]);

        $this->assertSame('Alterado', $ticketType->name);
        $this->assertSame($clinicA->id, $ticketType->clinic_id);
        $this->assertSame('N', $ticketType->prefix);
        $this->assertSame(10, $ticketType->priority);
        $this->assertTrue($ticketType->active);
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
}
