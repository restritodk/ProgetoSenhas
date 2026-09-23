<?php

namespace App\Livewire;

use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\UnitQueuePolicy;
use App\Services\NextTicketSelector;
use App\Services\OperationalContext;
use App\Services\UnitQueuePolicyResolver;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AttendantDeskQueue extends Component
{
    public string $search = '';

    public string $typeFilter = '';

    public function mount(OperationalContext $operationalContext): void
    {
        abort_unless(auth()->user()?->canAccessAttendantPanel(), 403);

        $user = auth()->user();
        $units = $this->operableUnits;

        if ($operationalContext->activeUnit($user, session()) === null && $units->count() === 1) {
            $operationalContext->setActiveUnit($user, $units->first(), session());
        }
    }

    public function updatedSearch(): void
    {
        unset($this->waitingTickets, $this->countsByType);
    }

    public function updatedTypeFilter(): void
    {
        unset($this->waitingTickets, $this->countsByType);
    }

    public function refreshQueue(): void
    {
        unset(
            $this->activeUnit,
            $this->activeDesk,
            $this->waitingTickets,
            $this->countsByType,
            $this->queueTicketTypes,
            $this->rankedPreview,
            $this->queuePolicy,
        );
    }

    /**
     * @return EloquentCollection<int, Unit>
     */
    #[Computed]
    public function operableUnits(): EloquentCollection
    {
        $user = auth()->user();

        $query = Unit::query()
            ->where('clinic_id', $user?->clinic_id)
            ->where('active', true)
            ->orderBy('name')
            ->orderBy('id');

        if (! $user?->isAdministrator()) {
            $query->whereIn('id', $user->units()->select('units.id'));
        }

        return $query->get();
    }

    #[Computed]
    public function activeUnit(): ?Unit
    {
        return app(OperationalContext::class)->activeUnit(auth()->user(), session());
    }

    #[Computed]
    public function activeDesk(): ?Desk
    {
        return app(OperationalContext::class)->activeDesk(auth()->user(), session());
    }

    #[Computed]
    public function queuePolicy(): ?UnitQueuePolicy
    {
        $unit = $this->activeUnit;

        if ($unit === null) {
            return null;
        }

        return app(UnitQueuePolicyResolver::class)->findForUnit($unit);
    }

    /**
     * Tipos ativos da clínica — cards dinâmicos (não hardcoded E/P/N).
     *
     * @return EloquentCollection<int, TicketType>
     */
    #[Computed]
    public function queueTicketTypes(): EloquentCollection
    {
        $unit = $this->activeUnit;

        if ($unit === null) {
            return new EloquentCollection;
        }

        return TicketType::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('active', true)
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'prefix', 'priority']);
    }

    /**
     * @return Collection<int|string, int>
     */
    #[Computed]
    public function countsByType(): Collection
    {
        $unit = $this->activeUnit;
        $desk = $this->activeDesk;

        if ($unit === null || $desk === null) {
            return collect();
        }

        return Ticket::query()
            ->selectRaw('ticket_type_id, count(*) as aggregate')
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('status', TicketStatus::WAITING)
            ->when(
                $desk->sector_id !== null,
                function ($query) use ($desk): void {
                    $query->where('sector_id', $desk->sector_id);
                },
            )
            ->where(function ($query) use ($desk): void {
                $query->whereNull('target_desk_id')
                    ->orWhere('target_desk_id', $desk->id);
            })
            ->groupBy('ticket_type_id')
            ->pluck('aggregate', 'ticket_type_id');
    }

    /**
     * Preview ordenado pela mesma política de CallNextTicket — não reserva senha.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function rankedPreview(): Collection
    {
        $unit = $this->activeUnit;
        $desk = $this->activeDesk;

        if ($unit === null || $desk === null) {
            return collect();
        }

        return app(NextTicketSelector::class)
            ->rankedWaitingQueue($unit, CarbonImmutable::now(config('app.timezone')), $desk);
    }

    /**
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function waitingTickets(): Collection
    {
        $ranked = $this->rankedPreview;
        $search = trim($this->search);
        $typeFilter = $this->typeFilter !== '' ? (int) $this->typeFilter : null;

        return $ranked
            ->filter(function (Ticket $ticket) use ($search, $typeFilter): bool {
                if ($typeFilter !== null && (int) $ticket->ticket_type_id !== $typeFilter) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $haystack = mb_strtolower(
                    $ticket->display_code.' '.($ticket->ticketType?->name ?? '').' '.($ticket->ticketType?->prefix ?? '')
                );

                return str_contains($haystack, mb_strtolower($search));
            })
            ->values();
    }

    public function formatWaitSeconds(Ticket $ticket): string
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $queuedAt = $ticket->queued_at ?? $ticket->issued_at;
        $seconds = max(0, (int) $queuedAt->diffInSeconds($now));

        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        if ($minutes >= 60) {
            return sprintf('%dh %02dmin', intdiv($minutes, 60), $minutes % 60);
        }

        return sprintf('%dmin %02ds', $minutes, $secs);
    }

    public function render(): View
    {
        return view('livewire.attendant-desk-queue', [
            'now' => CarbonImmutable::now(config('app.timezone')),
            'selector' => app(NextTicketSelector::class),
            'policy' => $this->queuePolicy,
        ]);
    }
}
