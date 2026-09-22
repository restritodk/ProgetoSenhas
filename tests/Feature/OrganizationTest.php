<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinic_owns_units_and_user_can_access_multiple_units(): void
    {
        $clinic = Clinic::factory()->create();
        $units = Unit::factory()->count(2)->for($clinic)->create();
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $user->units()->detach();
        foreach ($units as $unit) {
            $user->units()->attach($unit, ['clinic_id' => $clinic->id]);
        }

        $this->assertCount(2, $user->units);
        $this->assertTrue($clinic->units->contains($units[0]));
        $this->assertTrue($clinic->users->contains($user));
    }

    public function test_database_rejects_cross_tenant_pivot_insert(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $user = User::factory()->create(['clinic_id' => $clinicA->id]);
        $unitA = Unit::factory()->for($clinicA)->create();
        $unitB = Unit::factory()->for($clinicB)->create();

        DB::table('unit_user')->insert(['clinic_id' => $clinicA->id, 'user_id' => $user->id, 'unit_id' => $unitA->id]);
        $this->expectException(QueryException::class);
        DB::table('unit_user')->insert(['clinic_id' => $clinicA->id, 'user_id' => $user->id, 'unit_id' => $unitB->id]);
    }

    public function test_user_cannot_access_unit_from_another_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $user = User::factory()->create(['clinic_id' => $clinicA->id]);

        $this->assertFalse($user->hasAccessToUnit($unitB));
        $this->assertFalse(app(OperationalContext::class)->setActiveUnit($user, $unitB, $this->app['session']));
        $this->assertNull($this->app['session']->get(OperationalContext::SESSION_KEY));
    }

    public function test_user_without_clinic_cannot_operate(): void
    {
        $user = User::factory()->create(['clinic_id' => null]);
        $session = $this->app['session'];
        $session->put(OperationalContext::SESSION_KEY, 999999);

        $this->assertNull(app(OperationalContext::class)->activeUnit($user, $session));
        $this->assertNull($session->get(OperationalContext::SESSION_KEY));
    }

    public function test_tampered_active_unit_from_another_clinic_is_cleared(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $unitB = Unit::factory()->for($clinicB)->create();
        $user = User::factory()->create(['clinic_id' => $clinicA->id]);
        $this->app['session']->put(OperationalContext::SESSION_KEY, $unitB->id);

        $this->assertNull(app(OperationalContext::class)->activeUnit($user, $this->app['session']));
        $this->assertNull($this->app['session']->get(OperationalContext::SESSION_KEY));
    }

    public function test_inactive_entities_cannot_become_operational_context(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['active' => false]);
        $user = User::factory()->create(['clinic_id' => $clinic->id]);
        $user->units()->attach($unit, ['clinic_id' => $clinic->id]);

        $this->assertFalse(app(OperationalContext::class)->setActiveUnit($user, $unit, $this->app['session']));

        $clinic->update(['active' => false]);
        $unit->update(['active' => true]);
        $this->assertFalse(app(OperationalContext::class)->setActiveUnit($user, $unit, $this->app['session']));

        $clinic->update(['active' => true]);
        $user->forceFill(['active' => false])->save();
        $user->refresh();
        $this->assertFalse(app(OperationalContext::class)->setActiveUnit($user, $unit, $this->app['session']));
    }
}
