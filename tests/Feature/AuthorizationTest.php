<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_manages_only_own_clinic_and_units(): void
    {
        [$clinicA, $clinicB] = [Clinic::factory()->create(), Clinic::factory()->create()];
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $admin = User::factory()->create(['clinic_id' => $clinicA->id, 'role' => UserRole::ADMINISTRATOR]);

        $this->assertTrue($admin->can('update', $clinicA));
        $this->assertFalse($admin->can('update', $clinicB));
        $this->assertTrue($admin->can('update', $unitA));
        $this->assertFalse($admin->can('update', $unitB));
    }

    public function test_supervisor_and_attendant_only_view_linked_units(): void
    {
        $clinic = Clinic::factory()->create();
        $linked = Unit::factory()->for($clinic)->create();
        $unlinked = Unit::factory()->for($clinic)->create();
        $supervisor = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::SUPERVISOR]);
        $attendant = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $supervisor->units()->attach($linked, ['clinic_id' => $clinic->id]);
        $attendant->units()->attach($linked, ['clinic_id' => $clinic->id]);

        foreach ([$supervisor, $attendant] as $user) {
            $this->assertTrue($user->can('view', $linked));
            $this->assertFalse($user->can('view', $unlinked));
            $this->assertFalse($user->can('create', User::class));
        }
    }

    public function test_inactive_user_or_clinic_has_no_authorization(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR, 'active' => false]);

        $this->assertFalse($user->can('view', $unit));
        $user->forceFill(['active' => true])->save();
        $clinic->update(['active' => false]);
        $this->assertFalse($user->can('view', $unit));
    }
}
