<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitQueuePolicy;
use App\Models\UnitQueuePolicyProgress;
use App\Models\UnitQueuePolicyTypeSetting;
use App\QueueCriticalMode;
use Illuminate\Support\Collection;

class UnitQueuePolicyResolver
{
    /**
     * Persisted policy only. Null means CallNextTicket uses legacy aging ranking.
     */
    public function findForUnit(Unit $unit): ?UnitQueuePolicy
    {
        return UnitQueuePolicy::query()
            ->with(['typeSettings', 'criticalTicketType', 'distributionSourceTicketType', 'distributionTargetTicketType', 'progress'])
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->first();
    }

    /**
     * Lock persisted policy inside an open transaction. Does not create rows.
     */
    public function lockForUnit(Unit $unit): ?UnitQueuePolicy
    {
        $policy = UnitQueuePolicy::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->lockForUpdate()
            ->first();

        if ($policy !== null) {
            $policy->load(['typeSettings']);
        }

        return $policy;
    }

    /**
     * Policy for admin UI: persisted row or an unsaved draft with clinic defaults.
     */
    public function forUnit(Unit $unit): UnitQueuePolicy
    {
        $policy = $this->findForUnit($unit);

        if ($policy !== null) {
            return $policy;
        }

        return $this->makeDraft($unit);
    }

    public function lockProgress(UnitQueuePolicy $policy): UnitQueuePolicyProgress
    {
        $progress = UnitQueuePolicyProgress::query()
            ->where('clinic_id', $policy->clinic_id)
            ->where('unit_id', $policy->unit_id)
            ->lockForUpdate()
            ->first();

        if ($progress !== null) {
            return $progress;
        }

        $progress = new UnitQueuePolicyProgress;
        $progress->forceFill([
            'clinic_id' => $policy->clinic_id,
            'unit_id' => $policy->unit_id,
            'unit_queue_policy_id' => $policy->id,
            'source_calls_in_cycle' => 0,
            'target_calls_in_cycle' => 0,
            'cycle_version' => 1,
        ])->save();

        return $progress->refresh();
    }

    /**
     * Read-only progress view for ranking preview (no lock / no create).
     */
    public function progressForDisplay(UnitQueuePolicy $policy): UnitQueuePolicyProgress
    {
        if ($policy->exists && $policy->relationLoaded('progress') && $policy->progress !== null) {
            return $policy->progress;
        }

        if ($policy->exists) {
            $progress = UnitQueuePolicyProgress::query()
                ->where('unit_queue_policy_id', $policy->id)
                ->first();

            if ($progress !== null) {
                return $progress;
            }
        }

        $empty = new UnitQueuePolicyProgress;
        $empty->forceFill([
            'clinic_id' => $policy->clinic_id,
            'unit_id' => $policy->unit_id,
            'unit_queue_policy_id' => $policy->id ?? 0,
            'source_calls_in_cycle' => 0,
            'target_calls_in_cycle' => 0,
            'cycle_version' => 1,
        ]);

        return $empty;
    }

    /**
     * Update shared unit distribution progress after a successful call.
     * Critical always-first calls do not advance the proportional cycle.
     */
    public function recordCall(UnitQueuePolicy $policy, UnitQueuePolicyProgress $progress, Ticket $ticket): void
    {
        if (! $policy->isDistributionConfigured()) {
            return;
        }

        $typeId = (int) $ticket->ticket_type_id;

        if (
            $policy->usesAlwaysFirstCritical()
            && (int) $policy->critical_ticket_type_id === $typeId
        ) {
            return;
        }

        $sourceId = (int) $policy->distribution_source_ticket_type_id;
        $targetId = (int) $policy->distribution_target_ticket_type_id;
        $sourceCount = (int) $policy->distribution_source_count;
        $targetCount = (int) $policy->distribution_target_count;

        $sourceCalls = (int) $progress->source_calls_in_cycle;
        $targetCalls = (int) $progress->target_calls_in_cycle;

        if ($typeId === $targetId) {
            $targetCalls++;

            if ($targetCalls >= $targetCount) {
                $sourceCalls = 0;
                $targetCalls = 0;
            }
        } elseif ($typeId === $sourceId) {
            $sourceCalls = min($sourceCalls + 1, $sourceCount);
        }

        $progress->forceFill([
            'source_calls_in_cycle' => $sourceCalls,
            'target_calls_in_cycle' => $targetCalls,
            'unit_queue_policy_id' => $policy->id,
        ])->save();
    }

    public function resetProgress(UnitQueuePolicy $policy): UnitQueuePolicyProgress
    {
        $progress = $this->lockProgress($policy);

        $progress->forceFill([
            'source_calls_in_cycle' => 0,
            'target_calls_in_cycle' => 0,
            'cycle_version' => (int) $progress->cycle_version + 1,
            'unit_queue_policy_id' => $policy->id,
        ])->save();

        return $progress->refresh();
    }

    /**
     * Persist clinic defaults for a unit (Save first time / Restaurar padrão).
     *
     * @param  array{
     *     critical_ticket_type_id?: int|null,
     *     critical_mode?: QueueCriticalMode|string,
     *     distribution_enabled?: bool,
     *     distribution_source_ticket_type_id?: int|null,
     *     distribution_source_count?: int,
     *     distribution_target_ticket_type_id?: int|null,
     *     distribution_target_count?: int,
     *     anti_starvation_enabled?: bool,
     *     aging_interval_seconds?: int,
     *     aging_bonus_per_interval?: int,
     *     type_settings?: list<array{ticket_type_id: int, rescue_wait_seconds: int|null}>
     * }|null  $overrides
     */
    public function persist(Unit $unit, ?array $overrides = null): UnitQueuePolicy
    {
        $defaults = $this->defaultAttributes($unit);
        $attributes = array_merge($defaults, $overrides ?? []);
        unset($attributes['type_settings']);

        $policy = UnitQueuePolicy::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->lockForUpdate()
            ->first();

        if ($policy === null) {
            $policy = new UnitQueuePolicy;
            $policy->forceFill([
                'clinic_id' => $unit->clinic_id,
                'unit_id' => $unit->id,
            ]);
        }

        $policy->forceFill([
            'critical_ticket_type_id' => $attributes['critical_ticket_type_id'],
            'critical_mode' => $attributes['critical_mode'] instanceof QueueCriticalMode
                ? $attributes['critical_mode']
                : QueueCriticalMode::from((string) $attributes['critical_mode']),
            'distribution_enabled' => (bool) $attributes['distribution_enabled'],
            'distribution_source_ticket_type_id' => $attributes['distribution_source_ticket_type_id'],
            'distribution_source_count' => (int) $attributes['distribution_source_count'],
            'distribution_target_ticket_type_id' => $attributes['distribution_target_ticket_type_id'],
            'distribution_target_count' => (int) $attributes['distribution_target_count'],
            'anti_starvation_enabled' => (bool) $attributes['anti_starvation_enabled'],
            'aging_interval_seconds' => (int) $attributes['aging_interval_seconds'],
            'aging_bonus_per_interval' => (int) $attributes['aging_bonus_per_interval'],
        ])->save();

        $typeSettings = $overrides['type_settings'] ?? $defaults['type_settings'];
        $this->syncTypeSettings($policy, $typeSettings);
        $this->resetProgress($policy);

        return $policy->refresh()->load(['typeSettings', 'criticalTicketType', 'distributionSourceTicketType', 'distributionTargetTicketType', 'progress']);
    }

    /**
     * @param  list<array{ticket_type_id: int, rescue_wait_seconds: int|null}>  $typeSettings
     */
    public function syncTypeSettings(UnitQueuePolicy $policy, array $typeSettings): void
    {
        $keepIds = [];

        foreach ($typeSettings as $setting) {
            $ticketTypeId = (int) $setting['ticket_type_id'];
            $row = UnitQueuePolicyTypeSetting::query()
                ->where('unit_queue_policy_id', $policy->id)
                ->where('ticket_type_id', $ticketTypeId)
                ->first();

            if ($row === null) {
                $row = new UnitQueuePolicyTypeSetting;
                $row->forceFill([
                    'clinic_id' => $policy->clinic_id,
                    'unit_queue_policy_id' => $policy->id,
                    'ticket_type_id' => $ticketTypeId,
                ]);
            }

            $row->forceFill([
                'rescue_wait_seconds' => $setting['rescue_wait_seconds'],
            ])->save();

            $keepIds[] = $row->id;
        }

        UnitQueuePolicyTypeSetting::query()
            ->where('unit_queue_policy_id', $policy->id)
            ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
            ->delete();
    }

    /**
     * @return array{
     *     critical_ticket_type_id: int|null,
     *     critical_mode: QueueCriticalMode,
     *     distribution_enabled: bool,
     *     distribution_source_ticket_type_id: int|null,
     *     distribution_source_count: int,
     *     distribution_target_ticket_type_id: int|null,
     *     distribution_target_count: int,
     *     anti_starvation_enabled: bool,
     *     aging_interval_seconds: int,
     *     aging_bonus_per_interval: int,
     *     type_settings: list<array{ticket_type_id: int, rescue_wait_seconds: int|null}>
     * }
     */
    public function defaultAttributes(Unit $unit): array
    {
        $types = $this->clinicActiveTypes($unit);

        $critical = $types->sortByDesc(fn (TicketType $type): int => (int) $type->priority)->first();
        $source = $types->first(fn (TicketType $type): bool => $type->prefix === 'N')
            ?? $types->sortBy(fn (TicketType $type): int => (int) $type->priority)->first();
        $target = $types->first(fn (TicketType $type): bool => $type->prefix === 'P')
            ?? $types
                ->reject(fn (TicketType $type): bool => $source !== null && $type->id === $source->id)
                ->sortByDesc(fn (TicketType $type): int => (int) $type->priority)
                ->first(fn (TicketType $type): bool => $critical === null || $type->id !== $critical->id);

        if ($target !== null && $source !== null && $target->id === $source->id) {
            $target = null;
        }

        $typeSettings = $types->map(function (TicketType $type) use ($critical, $source): array {
            $priority = (int) $type->priority;
            $rescue = UnitQueuePolicy::DEFAULT_RESCUE_WAIT_MID_PRIORITY;

            if ($critical !== null && $type->id === $critical->id) {
                $rescue = UnitQueuePolicy::DEFAULT_RESCUE_WAIT_HIGH_PRIORITY;
            } elseif ($source !== null && $type->id === $source->id) {
                $rescue = UnitQueuePolicy::DEFAULT_RESCUE_WAIT_LOW_PRIORITY;
            }

            if ($priority >= 30) {
                $rescue = UnitQueuePolicy::DEFAULT_RESCUE_WAIT_HIGH_PRIORITY;
            } elseif ($priority <= 10) {
                $rescue = UnitQueuePolicy::DEFAULT_RESCUE_WAIT_LOW_PRIORITY;
            }

            return [
                'ticket_type_id' => (int) $type->id,
                'rescue_wait_seconds' => $rescue,
            ];
        })->values()->all();

        return [
            'critical_ticket_type_id' => $critical?->id,
            'critical_mode' => QueueCriticalMode::AlwaysFirst,
            'distribution_enabled' => $source !== null && $target !== null,
            'distribution_source_ticket_type_id' => $source?->id,
            'distribution_source_count' => UnitQueuePolicy::DEFAULT_DISTRIBUTION_SOURCE_COUNT,
            'distribution_target_ticket_type_id' => $target?->id,
            'distribution_target_count' => UnitQueuePolicy::DEFAULT_DISTRIBUTION_TARGET_COUNT,
            'anti_starvation_enabled' => true,
            'aging_interval_seconds' => UnitQueuePolicy::DEFAULT_AGING_INTERVAL_SECONDS,
            'aging_bonus_per_interval' => UnitQueuePolicy::DEFAULT_AGING_BONUS_PER_INTERVAL,
            'type_settings' => $typeSettings,
        ];
    }

    private function makeDraft(Unit $unit): UnitQueuePolicy
    {
        $defaults = $this->defaultAttributes($unit);

        $policy = new UnitQueuePolicy;
        $policy->forceFill([
            'clinic_id' => $unit->clinic_id,
            'unit_id' => $unit->id,
            'critical_ticket_type_id' => $defaults['critical_ticket_type_id'],
            'critical_mode' => $defaults['critical_mode'],
            'distribution_enabled' => $defaults['distribution_enabled'],
            'distribution_source_ticket_type_id' => $defaults['distribution_source_ticket_type_id'],
            'distribution_source_count' => $defaults['distribution_source_count'],
            'distribution_target_ticket_type_id' => $defaults['distribution_target_ticket_type_id'],
            'distribution_target_count' => $defaults['distribution_target_count'],
            'anti_starvation_enabled' => $defaults['anti_starvation_enabled'],
            'aging_interval_seconds' => $defaults['aging_interval_seconds'],
            'aging_bonus_per_interval' => $defaults['aging_bonus_per_interval'],
        ]);

        $policy->setRelation(
            'typeSettings',
            collect($defaults['type_settings'])->map(function (array $setting) use ($unit): UnitQueuePolicyTypeSetting {
                $row = new UnitQueuePolicyTypeSetting;
                $row->forceFill([
                    'clinic_id' => $unit->clinic_id,
                    'ticket_type_id' => $setting['ticket_type_id'],
                    'rescue_wait_seconds' => $setting['rescue_wait_seconds'],
                ]);

                return $row;
            }),
        );

        return $policy;
    }

    /**
     * @return Collection<int, TicketType>
     */
    private function clinicActiveTypes(Unit $unit): Collection
    {
        return TicketType::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('active', true)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }
}
