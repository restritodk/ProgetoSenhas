<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Non-destructive compatibility backfill:
     * - every existing Unit gets a default Sector (code PADRAO);
     * - desks/kiosks/tickets/ticket_calls inherit that sector;
     * - TVs subscribe to the default sector of their unit;
     * - sector ticket offers copy from unit_ticket_types (or all clinic types).
     *
     * Units that look like sectors (e.g. "Recepção") are NOT auto-moved under
     * another Unit — that requires an explicit admin decision.
     */
    public function up(): void
    {
        $now = now();

        $units = DB::table('units')->orderBy('id')->get();

        foreach ($units as $unit) {
            $existing = DB::table('sectors')
                ->where('clinic_id', $unit->clinic_id)
                ->where('unit_id', $unit->id)
                ->where('code', 'PADRAO')
                ->first();

            if ($existing === null) {
                $sectorId = DB::table('sectors')->insertGetId([
                    'clinic_id' => $unit->clinic_id,
                    'unit_id' => $unit->id,
                    'name' => 'Geral',
                    'code' => 'PADRAO',
                    'description' => 'Setor padrão criado automaticamente para compatibilidade operacional.',
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $sectorId = $existing->id;
            }

            DB::table('desks')
                ->where('clinic_id', $unit->clinic_id)
                ->where('unit_id', $unit->id)
                ->whereNull('sector_id')
                ->update(['sector_id' => $sectorId, 'updated_at' => $now]);

            DB::table('kiosks')
                ->where('clinic_id', $unit->clinic_id)
                ->where('unit_id', $unit->id)
                ->whereNull('sector_id')
                ->update(['sector_id' => $sectorId, 'updated_at' => $now]);

            DB::table('tickets')
                ->where('clinic_id', $unit->clinic_id)
                ->where('unit_id', $unit->id)
                ->whereNull('sector_id')
                ->update(['sector_id' => $sectorId, 'updated_at' => $now]);

            DB::table('ticket_calls')
                ->where('clinic_id', $unit->clinic_id)
                ->where('unit_id', $unit->id)
                ->whereNull('sector_id')
                ->update(['sector_id' => $sectorId, 'updated_at' => $now]);

            $panels = DB::table('display_panels')
                ->where('clinic_id', $unit->clinic_id)
                ->where('unit_id', $unit->id)
                ->pluck('id');

            foreach ($panels as $panelId) {
                $exists = DB::table('display_panel_sector')
                    ->where('display_panel_id', $panelId)
                    ->where('sector_id', $sectorId)
                    ->exists();

                if (! $exists) {
                    DB::table('display_panel_sector')->insert([
                        'clinic_id' => $unit->clinic_id,
                        'display_panel_id' => $panelId,
                        'sector_id' => $sectorId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $unitOffers = DB::table('unit_ticket_types')
                ->where('clinic_id', $unit->clinic_id)
                ->where('unit_id', $unit->id)
                ->orderBy('position')
                ->get();

            if ($unitOffers->isEmpty()) {
                $types = DB::table('ticket_types')
                    ->where('clinic_id', $unit->clinic_id)
                    ->where('active', true)
                    ->orderByDesc('priority')
                    ->orderBy('name')
                    ->get();

                $position = 0;
                foreach ($types as $type) {
                    $this->upsertSectorOffer($unit->clinic_id, $sectorId, $type->id, true, null, $position++, $now);
                }
            } else {
                foreach ($unitOffers as $offer) {
                    $this->upsertSectorOffer(
                        $unit->clinic_id,
                        $sectorId,
                        $offer->ticket_type_id,
                        (bool) $offer->active,
                        $offer->display_name,
                        (int) $offer->position,
                        $now,
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // Keep historical sector rows; no destructive rollback of operational sector_id values.
    }

    private function upsertSectorOffer(
        int $clinicId,
        int $sectorId,
        int $ticketTypeId,
        bool $active,
        ?string $displayName,
        int $position,
        mixed $now,
    ): void {
        $exists = DB::table('sector_ticket_types')
            ->where('sector_id', $sectorId)
            ->where('ticket_type_id', $ticketTypeId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('sector_ticket_types')->insert([
            'clinic_id' => $clinicId,
            'sector_id' => $sectorId,
            'ticket_type_id' => $ticketTypeId,
            'active' => $active,
            'display_name' => $displayName,
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
