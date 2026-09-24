<?php

namespace Tests\Feature;

use App\Actions\CreateDisplayPanel;
use App\Actions\CreateKiosk;
use App\Actions\RegenerateDisplayPanelToken;
use App\Actions\RegenerateKioskToken;
use App\Models\Clinic;
use App\Models\DisplayPanel;
use App\Models\Kiosk;
use App\Models\Sector;
use App\Models\Unit;
use App\Models\User;
use App\Support\PublicAccessCode;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicAccessCodeUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_panel_and_kiosk_receive_random_public_codes(): void
    {
        $admin = $this->administrator();
        $unit = Unit::factory()->for($admin->clinic)->create();

        $this->actingAs($admin);
        $panel = app(CreateDisplayPanel::class)->handle($admin, [
            'name' => 'TV Recepção',
            'code' => 'TV-REC',
            'unit_id' => $unit->id,
            'sector_ids' => [],
            'active' => true,
        ]);
        $kiosk = app(CreateKiosk::class)->handle($admin, [
            'name' => 'Totem Recepção',
            'code' => 'TOT-REC',
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        $this->assertTrue(PublicAccessCode::isPanelCode($panel->public_code));
        $this->assertTrue(PublicAccessCode::isKioskCode($kiosk->public_code));
        $this->assertSame(64, strlen($panel->public_token));
        $this->assertSame(64, strlen($kiosk->public_token));
        $this->assertNotSame((string) $panel->id, $panel->public_code);
        $this->assertNotSame((string) $kiosk->id, $kiosk->public_code);
        $this->assertStringContainsString('/painel/'.$panel->public_code, $panel->publicUrl());
        $this->assertStringContainsString('/totem/'.$kiosk->public_code, $kiosk->publicUrl());
        $this->assertStringNotContainsString($panel->public_token, $panel->publicUrl());
        $this->assertStringNotContainsString($kiosk->public_token, $kiosk->publicUrl());
    }

    public function test_two_panels_do_not_share_the_same_public_code(): void
    {
        $codes = collect(range(1, 8))->map(function (): string {
            return DisplayPanel::factory()->create()->public_code;
        });

        $this->assertSame($codes->count(), $codes->unique()->count());
    }

    public function test_short_urls_resolve_and_numeric_id_does_not(): void
    {
        $panel = DisplayPanel::factory()->create(['active' => true]);
        $kiosk = Kiosk::factory()->create(['active' => true]);

        $this->get(route('tv.panel', $panel->public_code))->assertOk();
        $this->get(route('kiosk.panel', $kiosk->public_code))->assertOk();

        $this->get('/painel/'.$panel->id)->assertNotFound();
        $this->get('/totem/'.$kiosk->id)->assertNotFound();
        $this->get('/painel/TV-INVALID00')->assertNotFound();
        $this->get('/totem/TOT-INVALID00')->assertNotFound();
    }

    public function test_legacy_long_token_urls_remain_compatible(): void
    {
        $panel = DisplayPanel::factory()->create(['active' => true]);
        $kiosk = Kiosk::factory()->create(['active' => true]);

        $this->get(route('tv.panel', $panel->public_token))->assertOk();
        $this->get(route('kiosk.panel', $kiosk->public_token))->assertOk();
    }

    public function test_regenerate_invalidates_previous_short_and_legacy_links(): void
    {
        $admin = $this->administrator();
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => Unit::factory()->for($admin->clinic)->create()->id,
            'active' => true,
        ]);
        $kiosk = Kiosk::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => $panel->unit_id,
            'active' => true,
        ]);

        $oldPanelCode = $panel->public_code;
        $oldPanelToken = $panel->public_token;
        $oldKioskCode = $kiosk->public_code;
        $oldKioskToken = $kiosk->public_token;

        $this->actingAs($admin);
        $panel = app(RegenerateDisplayPanelToken::class)->handle($admin, $panel);
        $kiosk = app(RegenerateKioskToken::class)->handle($admin, $kiosk);

        $this->assertNotSame($oldPanelCode, $panel->public_code);
        $this->assertNotSame($oldPanelToken, $panel->public_token);
        $this->assertNotSame($oldKioskCode, $kiosk->public_code);
        $this->assertNotSame($oldKioskToken, $kiosk->public_token);

        $this->get(route('tv.panel', $oldPanelCode))->assertNotFound();
        $this->get(route('tv.panel', $oldPanelToken))->assertNotFound();
        $this->get(route('tv.panel', $panel->public_code))->assertOk();

        $this->get(route('kiosk.panel', $oldKioskCode))->assertNotFound();
        $this->get(route('kiosk.panel', $oldKioskToken))->assertNotFound();
        $this->get(route('kiosk.panel', $kiosk->public_code))->assertOk();
    }

    public function test_regenerate_does_not_change_unit_or_sector_links(): void
    {
        $admin = $this->administrator();
        $unit = Unit::factory()->for($admin->clinic)->create();
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $admin->clinic_id,
            'unit_id' => $unit->id,
        ]);
        $panel->sectors()->sync([
            Sector::factory()->create([
                'clinic_id' => $admin->clinic_id,
                'unit_id' => $unit->id,
            ])->id => ['clinic_id' => $admin->clinic_id],
        ]);
        $sectorIds = $panel->sectorIds();

        $this->actingAs($admin);
        $updated = app(RegenerateDisplayPanelToken::class)->handle($admin, $panel);

        $this->assertSame($unit->id, $updated->unit_id);
        $this->assertSame($sectorIds, $updated->sectorIds());
    }

    public function test_public_code_columns_are_unique(): void
    {
        $this->assertTrue(Schema::hasColumn('display_panels', 'public_code'));
        $this->assertTrue(Schema::hasColumn('kiosks', 'public_code'));

        $indexes = collect(Schema::getIndexes('display_panels'))
            ->filter(fn (array $index): bool => in_array('public_code', $index['columns'], true) && ($index['unique'] ?? false));
        $this->assertFalse($indexes->isEmpty());

        $kioskIndexes = collect(Schema::getIndexes('kiosks'))
            ->filter(fn (array $index): bool => in_array('public_code', $index['columns'], true) && ($index['unique'] ?? false));
        $this->assertFalse($kioskIndexes->isEmpty());
    }

    public function test_invalid_legacy_token_still_returns_not_found_without_leaking_data(): void
    {
        $response = $this->get(route('tv.panel', str_repeat('a', 64)));
        $response->assertNotFound();
        $response->assertDontSee('clinic_id');
        $response->assertDontSee('Hospital');
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
