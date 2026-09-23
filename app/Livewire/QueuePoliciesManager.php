<?php

namespace App\Livewire;

use App\Actions\RestoreUnitQueuePolicyDefaults;
use App\Actions\SaveUnitQueuePolicy;
use App\Models\Desk;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitQueuePolicy;
use App\QueueCriticalMode;
use App\Services\UnitQueuePolicyResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class QueuePoliciesManager extends Component
{
    public string $activeTab = 'rules';

    public ?int $selectedUnitId = null;

    public ?int $criticalTicketTypeId = null;

    public string $criticalMode = 'always_first';

    public bool $distributionEnabled = true;

    public ?int $distributionSourceTicketTypeId = null;

    public string $distributionSourceCount = '3';

    public ?int $distributionTargetTicketTypeId = null;

    public string $distributionTargetCount = '1';

    public bool $antiStarvationEnabled = true;

    public string $agingIntervalSeconds = '60';

    public string $agingBonusPerInterval = '5';

    /** @var array<int|string, string> ticket_type_id => minutes (UI) */
    public array $rescueWaitMinutes = [];

    public bool $showHelp = false;

    public bool $showRestoreConfirm = false;

    public bool $showHowItWorks = true;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public bool $saving = false;

    public function mount(UnitQueuePolicyResolver $resolver): void
    {
        $this->authorize('queue_policy.view');

        $units = $this->units;
        $this->selectedUnitId = $units->first()?->id;
        $this->loadPolicy($resolver);
    }

    public function updatedSelectedUnitId(UnitQueuePolicyResolver $resolver): void
    {
        $this->clearStatusMessage();
        $this->loadPolicy($resolver);
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['rules', 'types', 'advanced'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function openHelp(): void
    {
        $this->showHelp = true;
    }

    public function closeHelp(): void
    {
        $this->showHelp = false;
    }

    public function dismissHowItWorks(): void
    {
        $this->showHowItWorks = false;
    }

    public function confirmRestore(): void
    {
        $this->showRestoreConfirm = true;
    }

    public function cancelRestore(): void
    {
        $this->showRestoreConfirm = false;
    }

    public function clearStatusMessage(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
    }

    public function save(SaveUnitQueuePolicy $saveUnitQueuePolicy): void
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $unit = $this->unitForActor();
        $this->authorize('queue_policy.update');

        $this->saving = true;
        $this->clearStatusMessage();

        try {
            $saveUnitQueuePolicy->handle($actor, $unit, $this->payload());
            $this->statusMessage = 'Configurações de fila salvas com sucesso.';
            $this->loadPolicy(app(UnitQueuePolicyResolver::class));
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first()
                ?? 'Não foi possível salvar as configurações.';
            throw $exception;
        } finally {
            $this->saving = false;
        }
    }

    public function restoreDefaults(RestoreUnitQueuePolicyDefaults $restore): void
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $unit = $this->unitForActor();
        $this->authorize('queue_policy.update');

        $this->saving = true;
        $this->clearStatusMessage();
        $this->showRestoreConfirm = false;

        try {
            $restore->handle($actor, $unit);
            $this->statusMessage = 'Configurações padrão restauradas.';
            $this->loadPolicy(app(UnitQueuePolicyResolver::class));
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first()
                ?? 'Não foi possível restaurar o padrão.';
            throw $exception;
        } finally {
            $this->saving = false;
        }
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function units(): Collection
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        return Unit::query()
            ->where('clinic_id', $actor->clinic_id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'clinic_id', 'name']);
    }

    /**
     * @return Collection<int, TicketType>
     */
    #[Computed]
    public function ticketTypes(): Collection
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        return TicketType::query()
            ->where('clinic_id', $actor->clinic_id)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get(['id', 'clinic_id', 'name', 'prefix', 'priority', 'active']);
    }

    /**
     * @return Collection<int, TicketType>
     */
    #[Computed]
    public function activeTicketTypes(): Collection
    {
        return $this->ticketTypes->where('active', true)->values();
    }

    /**
     * @return array{ticket_types: int, active_types: int, units_with_policy: int, active_desks: int}
     */
    #[Computed]
    public function summary(): array
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        return [
            'ticket_types' => TicketType::query()->where('clinic_id', $actor->clinic_id)->count(),
            'active_types' => TicketType::query()->where('clinic_id', $actor->clinic_id)->where('active', true)->count(),
            'units_with_policy' => UnitQueuePolicy::query()->where('clinic_id', $actor->clinic_id)->count(),
            'active_desks' => Desk::query()
                ->where('clinic_id', $actor->clinic_id)
                ->where('active', true)
                ->when($this->selectedUnitId, fn ($query) => $query->where('unit_id', $this->selectedUnitId))
                ->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.queue-policies-manager', [
            'criticalModes' => QueueCriticalMode::cases(),
            'ticketTypesUrl' => route('ticket-types.index'),
            'unitTicketTypesUrl' => route('unit-ticket-types.index'),
        ]);
    }

    private function loadPolicy(UnitQueuePolicyResolver $resolver): void
    {
        if ($this->selectedUnitId === null) {
            return;
        }

        $unit = $this->unitForActor();
        $policy = $resolver->forUnit($unit);

        $this->criticalTicketTypeId = $policy->critical_ticket_type_id;
        $this->criticalMode = $policy->critical_mode instanceof QueueCriticalMode
            ? $policy->critical_mode->value
            : (string) $policy->critical_mode;
        $this->distributionEnabled = (bool) $policy->distribution_enabled;
        $this->distributionSourceTicketTypeId = $policy->distribution_source_ticket_type_id;
        $this->distributionSourceCount = (string) $policy->distribution_source_count;
        $this->distributionTargetTicketTypeId = $policy->distribution_target_ticket_type_id;
        $this->distributionTargetCount = (string) $policy->distribution_target_count;
        $this->antiStarvationEnabled = (bool) $policy->anti_starvation_enabled;
        $this->agingIntervalSeconds = (string) $policy->aging_interval_seconds;
        $this->agingBonusPerInterval = (string) $policy->aging_bonus_per_interval;

        $rescue = [];
        foreach ($policy->typeSettings as $setting) {
            $seconds = $setting->rescue_wait_seconds;
            $rescue[(string) $setting->ticket_type_id] = $seconds !== null
                ? (string) intdiv((int) $seconds, 60)
                : '';
        }

        foreach ($this->activeTicketTypes as $type) {
            $key = (string) $type->id;
            if (! array_key_exists($key, $rescue)) {
                $rescue[$key] = (string) intdiv(UnitQueuePolicy::DEFAULT_RESCUE_WAIT_MID_PRIORITY, 60);
            }
        }

        $this->rescueWaitMinutes = $rescue;

        unset($this->summary, $this->ticketTypes, $this->activeTicketTypes);
    }

    private function unitForActor(): Unit
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null || $this->selectedUnitId === null, 404);

        $unit = Unit::query()
            ->where('clinic_id', $actor->clinic_id)
            ->whereKey($this->selectedUnitId)
            ->first();

        abort_if($unit === null, 404);

        return $unit;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $typeSettings = [];
        foreach ($this->rescueWaitMinutes as $ticketTypeId => $minutes) {
            $minutes = trim((string) $minutes);
            $typeSettings[(int) $ticketTypeId] = $minutes === ''
                ? null
                : max(1, (int) $minutes) * 60;
        }

        return [
            'critical_ticket_type_id' => $this->criticalTicketTypeId,
            'critical_mode' => $this->criticalMode,
            'distribution_enabled' => $this->distributionEnabled,
            'distribution_source_ticket_type_id' => $this->distributionSourceTicketTypeId,
            'distribution_source_count' => (int) $this->distributionSourceCount,
            'distribution_target_ticket_type_id' => $this->distributionTargetTicketTypeId,
            'distribution_target_count' => (int) $this->distributionTargetCount,
            'anti_starvation_enabled' => $this->antiStarvationEnabled,
            'aging_interval_seconds' => (int) $this->agingIntervalSeconds,
            'aging_bonus_per_interval' => (int) $this->agingBonusPerInterval,
            'type_settings' => $typeSettings,
        ];
    }
}
