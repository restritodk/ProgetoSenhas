<?php

namespace App\Actions;

use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitQueuePolicy;
use App\Models\User;
use App\QueueCriticalMode;
use App\Services\UnitQueuePolicyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveUnitQueuePolicy
{
    public function __construct(
        private UnitQueuePolicyResolver $policyResolver,
    ) {}

    /**
     * @param  array{
     *     critical_ticket_type_id: int|null|string,
     *     critical_mode: string,
     *     distribution_enabled: bool,
     *     distribution_source_ticket_type_id: int|null|string,
     *     distribution_source_count: int|string,
     *     distribution_target_ticket_type_id: int|null|string,
     *     distribution_target_count: int|string,
     *     anti_starvation_enabled: bool,
     *     aging_interval_seconds: int|string,
     *     aging_bonus_per_interval: int|string,
     *     type_settings?: array<int|string, int|string|null>
     * }  $input
     */
    public function handle(User $actor, Unit $unit, array $input): UnitQueuePolicy
    {
        abort_if($unit->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('queue_policy.update');

        $validated = $this->validate($actor, $unit, $input);

        return DB::transaction(function () use ($unit, $validated): UnitQueuePolicy {
            return $this->policyResolver->persist($unit, $validated);
        });
    }

    /**
     * @param  array<string, mixed>  $input
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
    private function validate(User $actor, Unit $unit, array $input): array
    {
        $clinicTypeIds = TicketType::query()
            ->where('clinic_id', $actor->clinic_id)
            ->where('active', true)
            ->pluck('id')
            ->all();

        $validator = Validator::make($input, [
            'critical_ticket_type_id' => ['nullable', 'integer', Rule::in($clinicTypeIds)],
            'critical_mode' => ['required', Rule::enum(QueueCriticalMode::class)],
            'distribution_enabled' => ['required', 'boolean'],
            'distribution_source_ticket_type_id' => ['nullable', 'integer', Rule::in($clinicTypeIds)],
            'distribution_source_count' => [
                'required',
                'integer',
                'min:'.UnitQueuePolicy::MIN_DISTRIBUTION_COUNT,
                'max:'.UnitQueuePolicy::MAX_DISTRIBUTION_COUNT,
            ],
            'distribution_target_ticket_type_id' => ['nullable', 'integer', Rule::in($clinicTypeIds)],
            'distribution_target_count' => [
                'required',
                'integer',
                'min:'.UnitQueuePolicy::MIN_DISTRIBUTION_COUNT,
                'max:'.UnitQueuePolicy::MAX_DISTRIBUTION_COUNT,
            ],
            'anti_starvation_enabled' => ['required', 'boolean'],
            'aging_interval_seconds' => [
                'required',
                'integer',
                'min:'.UnitQueuePolicy::MIN_AGING_INTERVAL_SECONDS,
                'max:'.UnitQueuePolicy::MAX_AGING_INTERVAL_SECONDS,
            ],
            'aging_bonus_per_interval' => [
                'required',
                'integer',
                'min:'.UnitQueuePolicy::MIN_AGING_BONUS,
                'max:'.UnitQueuePolicy::MAX_AGING_BONUS,
            ],
            'type_settings' => ['nullable', 'array'],
            'type_settings.*' => [
                'nullable',
                'integer',
                'min:'.UnitQueuePolicy::MIN_RESCUE_WAIT_SECONDS,
                'max:'.UnitQueuePolicy::MAX_RESCUE_WAIT_SECONDS,
            ],
        ], [
            'distribution_source_ticket_type_id.in' => 'O tipo de origem da distribuição é inválido para esta clínica.',
            'distribution_target_ticket_type_id.in' => 'O tipo de destino da distribuição é inválido para esta clínica.',
            'critical_ticket_type_id.in' => 'O tipo crítico selecionado é inválido para esta clínica.',
        ]);

        $validated = $validator->validate();

        $sourceId = isset($validated['distribution_source_ticket_type_id'])
            ? (int) $validated['distribution_source_ticket_type_id']
            : null;
        $targetId = isset($validated['distribution_target_ticket_type_id'])
            ? (int) $validated['distribution_target_ticket_type_id']
            : null;

        if ((bool) $validated['distribution_enabled']) {
            if ($sourceId === null || $targetId === null) {
                throw ValidationException::withMessages([
                    'distribution_source_ticket_type_id' => 'Selecione os tipos de origem e destino da distribuição.',
                ]);
            }

            if ($sourceId === $targetId) {
                throw ValidationException::withMessages([
                    'distribution_target_ticket_type_id' => 'Origem e destino da distribuição devem ser tipos diferentes.',
                ]);
            }
        }

        $typeSettings = [];
        foreach ($validated['type_settings'] ?? [] as $ticketTypeId => $seconds) {
            $ticketTypeId = (int) $ticketTypeId;

            if (! in_array($ticketTypeId, $clinicTypeIds, true)) {
                throw ValidationException::withMessages([
                    'type_settings' => 'Um dos tipos na proteção contra espera excessiva é inválido.',
                ]);
            }

            $typeSettings[] = [
                'ticket_type_id' => $ticketTypeId,
                'rescue_wait_seconds' => $seconds !== null && $seconds !== '' ? (int) $seconds : null,
            ];
        }

        if ($typeSettings === []) {
            $typeSettings = $this->policyResolver->defaultAttributes($unit)['type_settings'];
        }

        return [
            'critical_ticket_type_id' => isset($validated['critical_ticket_type_id'])
                ? (int) $validated['critical_ticket_type_id']
                : null,
            'critical_mode' => QueueCriticalMode::from($validated['critical_mode']),
            'distribution_enabled' => (bool) $validated['distribution_enabled'],
            'distribution_source_ticket_type_id' => $sourceId,
            'distribution_source_count' => (int) $validated['distribution_source_count'],
            'distribution_target_ticket_type_id' => $targetId,
            'distribution_target_count' => (int) $validated['distribution_target_count'],
            'anti_starvation_enabled' => (bool) $validated['anti_starvation_enabled'],
            'aging_interval_seconds' => (int) $validated['aging_interval_seconds'],
            'aging_bonus_per_interval' => (int) $validated['aging_bonus_per_interval'],
            'type_settings' => $typeSettings,
        ];
    }
}
