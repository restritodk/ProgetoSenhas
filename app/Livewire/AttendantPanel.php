<?php

namespace App\Livewire;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\CompleteTicketService;
use App\Actions\MarkTicketNoShow;
use App\Actions\RecallTicket;
use App\Actions\ReleaseDesk;
use App\Actions\StartTicketService;
use App\Actions\TransferTicket;
use App\Models\Desk;
use App\Models\DeskAssignment;
use App\Models\Kiosk;
use App\Models\Sector;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Services\NextTicketSelector;
use App\Services\OperationalContext;
use App\Support\DeskLease;
use App\TicketStatus;
use App\TicketTransferType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AttendantPanel extends Component
{
    public string $statusMessage = '';

    public string $errorMessage = '';

    public bool $showTransferModal = false;

    public bool $showNoShowModal = false;

    public string $transferDestination = 'queue';

    public ?int $transferToDeskId = null;

    public string $transferReason = '';

    public function mount(OperationalContext $operationalContext): void
    {
        abort_unless(auth()->user()?->canAccessAttendantPanel(), 403);

        $user = auth()->user();
        $units = $this->operableUnits;

        if ($operationalContext->activeUnit($user, session()) === null && $units->count() === 1) {
            $operationalContext->setActiveUnit($user, $units->first(), session());
        }
    }

    public function selectUnit(int $unitId, OperationalContext $operationalContext, ReleaseDesk $releaseDesk): void
    {
        $user = auth()->user();
        $unit = $this->operableUnits->firstWhere('id', $unitId);
        abort_if($unit === null, 404);

        $releaseDesk->handle($user);
        abort_unless($operationalContext->setActiveUnit($user, $unit, session()), 403);

        $this->clearMessages();
        $this->closeTransferModal();
        $this->closeNoShowModal();
        $this->forgetComputed();
    }

    public function clearUnit(OperationalContext $operationalContext, ReleaseDesk $releaseDesk): void
    {
        $releaseDesk->handle(auth()->user());
        $operationalContext->clear(session());
        $this->clearMessages();
        $this->closeTransferModal();
        $this->closeNoShowModal();
        $this->forgetComputed();
    }

    public function clearDesk(ReleaseDesk $releaseDesk): void
    {
        $releaseDesk->handle(auth()->user());
        $this->clearMessages();
        $this->closeTransferModal();
        $this->closeNoShowModal();
        $this->statusMessage = 'Mesa liberada.';
        $this->forgetComputed();
    }

    public function selectDesk(int $deskId, ClaimDesk $claimDesk): void
    {
        $user = auth()->user();
        $unit = $this->activeUnit;
        abort_if($unit === null, 403);
        abort_unless($user?->canOperateUnit($unit) ?? false, 403);

        $desk = Desk::query()
            ->where('clinic_id', $user->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('active', true)
            ->whereKey($deskId)
            ->first();

        if ($desk === null) {
            $this->errorMessage = 'A mesa selecionada não pertence à unidade atual ou está indisponível.';
            $this->statusMessage = '';
            $this->forgetComputed();

            return;
        }

        try {
            $claimDesk->handle($user, $desk);
            $this->statusMessage = 'Mesa '.$desk->name.' ativada.';
            $this->errorMessage = '';
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first()
                ?? 'Não foi possível ativar a mesa.';
            $this->statusMessage = '';
        }

        $this->forgetComputed();
    }

    public function callNext(CallNextTicket $callNextTicket): void
    {
        $this->authorize('tickets.call');

        try {
            $ticket = $callNextTicket->handle(auth()->user());

            if ($ticket === null) {
                $this->errorMessage = 'Não há senhas aguardando nesta unidade.';
                $this->statusMessage = '';
            } else {
                $this->statusMessage = 'Senha '.$ticket->display_code.' chamada.';
                $this->errorMessage = '';
            }
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível chamar a próxima senha.';
            $this->statusMessage = '';
        }

        $this->forgetComputed();
    }

    public function recall(RecallTicket $recallTicket): void
    {
        $ticket = $this->currentTicket;
        abort_if($ticket === null, 404);
        $this->authorize('recall', $ticket);

        try {
            $recallTicket->handle(auth()->user(), $ticket);
            $this->statusMessage = 'Senha '.$ticket->display_code.' rechamada.';
            $this->errorMessage = '';
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível rechamar.';
            $this->statusMessage = '';
        }

        $this->forgetComputed();
    }

    public function startService(StartTicketService $startTicketService): void
    {
        $ticket = $this->currentTicket;
        abort_if($ticket === null, 404);
        $this->authorize('startService', $ticket);

        try {
            $startTicketService->handle(auth()->user(), $ticket);
            $this->statusMessage = 'Atendimento iniciado.';
            $this->errorMessage = '';
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível iniciar o atendimento.';
            $this->statusMessage = '';
        }

        $this->forgetComputed();
    }

    public function complete(CompleteTicketService $completeTicketService): void
    {
        $ticket = $this->currentTicket;
        abort_if($ticket === null, 404);
        $this->authorize('complete', $ticket);

        try {
            $completeTicketService->handle(auth()->user(), $ticket);
            $this->statusMessage = 'Atendimento finalizado.';
            $this->errorMessage = '';
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível finalizar.';
            $this->statusMessage = '';
        }

        $this->forgetComputed();
    }

    public function openNoShowModal(): void
    {
        $ticket = $this->currentTicket;
        abort_if($ticket === null, 404);
        $this->authorize('markNoShow', $ticket);
        $this->showNoShowModal = true;
    }

    public function closeNoShowModal(): void
    {
        $this->showNoShowModal = false;
    }

    public function noShow(MarkTicketNoShow $markTicketNoShow): void
    {
        $ticket = $this->currentTicket;
        abort_if($ticket === null, 404);
        $this->authorize('markNoShow', $ticket);

        try {
            $markTicketNoShow->handle(auth()->user(), $ticket);
            $this->statusMessage = 'Não comparecimento registrado.';
            $this->errorMessage = '';
            $this->closeNoShowModal();
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível registrar o não comparecimento.';
            $this->statusMessage = '';
        }

        $this->forgetComputed();
    }

    public function formatWaitDuration(?\DateTimeInterface $from, ?\DateTimeInterface $to = null): string
    {
        if ($from === null) {
            return '—';
        }

        $start = CarbonImmutable::parse($from)->timezone(config('app.timezone'));
        $end = $to !== null
            ? CarbonImmutable::parse($to)->timezone(config('app.timezone'))
            : CarbonImmutable::now(config('app.timezone'));
        $seconds = max(0, (int) $start->diffInSeconds($end));
        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        if ($minutes >= 60) {
            return sprintf('%dh %02dmin', intdiv($minutes, 60), $minutes % 60);
        }

        return sprintf('%dmin %02ds', $minutes, $secs);
    }

    public function waitingCountForType(int $ticketTypeId): int
    {
        return (int) ($this->queueCountsByType[$ticketTypeId] ?? 0);
    }

    public function openTransferModal(): void
    {
        $ticket = $this->currentTicket;
        abort_if($ticket === null, 404);
        $this->authorize('transfer', $ticket);

        $this->showTransferModal = true;
        $this->transferDestination = 'queue';
        $this->transferToDeskId = null;
        $this->transferReason = '';
        $this->resetErrorBag();
    }

    public function closeTransferModal(): void
    {
        $this->showTransferModal = false;
        $this->transferDestination = 'queue';
        $this->transferToDeskId = null;
        $this->transferReason = '';
        $this->resetErrorBag('transferToDeskId', 'transferReason', 'transferDestination', 'ticket');
    }

    public function pollPaused(): bool
    {
        return $this->showTransferModal || $this->showNoShowModal;
    }

    public function transfer(TransferTicket $transferTicket): void
    {
        $ticket = $this->currentTicket;
        abort_if($ticket === null, 404);
        $this->authorize('transfer', $ticket);

        $this->validate([
            'transferDestination' => ['required', 'in:queue,desk'],
            'transferToDeskId' => [
                'nullable',
                'integer',
                'required_if:transferDestination,desk',
            ],
            'transferReason' => ['nullable', 'string', 'max:255'],
        ], [
            'transferToDeskId.required_if' => 'Selecione a mesa de destino.',
        ]);

        $type = $this->transferDestination === 'desk'
            ? TicketTransferType::DESK
            : TicketTransferType::QUEUE;

        try {
            $transferred = $transferTicket->handle(
                auth()->user(),
                $ticket,
                $type,
                $type === TicketTransferType::DESK ? $this->transferToDeskId : null,
                $this->transferReason !== '' ? $this->transferReason : null,
            );

            $destinationLabel = $type === TicketTransferType::DESK
                ? ($transferred->targetDesk?->name ?? 'mesa selecionada')
                : 'a fila';

            $prefix = $type === TicketTransferType::DESK ? 'para ' : 'para ';
            $this->statusMessage = 'Senha '.$transferred->display_code.' transferida '.$prefix.$destinationLabel.'.';
            $this->errorMessage = '';
            $this->closeTransferModal();
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível transferir a senha.';
            $this->statusMessage = '';
        }

        $this->forgetComputed();
    }

    public function refreshPanel(): void
    {
        $this->forgetComputed();
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

    /**
     * @return EloquentCollection<int, Desk>
     */
    #[Computed]
    public function availableDesks(): EloquentCollection
    {
        $unit = $this->activeUnit;

        if ($unit === null) {
            return new EloquentCollection;
        }

        return Desk::query()
            ->with('sector:id,unit_id,name,code')
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('active', true)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Active desks for the selected unit with occupancy state for selection UI.
     *
     * @return Collection<int, array{desk: Desk, available: bool}>
     */
    #[Computed]
    public function deskSelectionCards(): Collection
    {
        $desks = $this->availableDesks;

        if ($desks->isEmpty()) {
            return collect();
        }

        // Opportunistic cleanup of abandoned claims (does not touch tickets).
        DeskLease::purgeExpired();

        $occupiedDeskIds = DeskAssignment::query()
            ->whereIn('desk_id', $desks->modelKeys())
            ->where('user_id', '!=', auth()->id())
            ->where('last_seen_at', '>', DeskLease::expiresBefore())
            ->pluck('desk_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $desks->map(fn (Desk $desk): array => [
            'desk' => $desk,
            'available' => ! in_array((int) $desk->id, $occupiedDeskIds, true),
        ])->values();
    }

    public function hasSelectableDesks(): bool
    {
        return $this->deskSelectionCards->contains(fn (array $card): bool => $card['available']);
    }

    /**
     * Mesas elegíveis como destino de transferência (exclui a mesa atual).
     *
     * @return EloquentCollection<int, Desk>
     */
    #[Computed]
    public function transferDestinationDesks(): EloquentCollection
    {
        $unit = $this->activeUnit;
        $currentDesk = $this->activeDesk;

        if ($unit === null || $currentDesk === null) {
            return new EloquentCollection;
        }

        return Desk::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('active', true)
            ->whereKeyNot($currentDesk->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function currentTicket(): ?Ticket
    {
        $desk = $this->activeDesk;

        if ($desk === null) {
            return null;
        }

        return Ticket::query()
            ->with(['ticketType:id,name,prefix,priority'])
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->where('current_desk_id', $desk->id)
            ->whereIn('status', [TicketStatus::CALLED, TicketStatus::IN_SERVICE])
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Próximas da fila — preview fixo de 5 (mesma ordem de CallNextTicket), sem reservar.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function upcomingQueue(): Collection
    {
        $unit = $this->activeUnit;
        $desk = $this->activeDesk;

        if ($unit === null || $desk === null) {
            return collect();
        }

        return app(NextTicketSelector::class)
            ->rankedWaitingQueue($unit, CarbonImmutable::now(config('app.timezone')), $desk)
            ->take(5)
            ->values();
    }

    #[Computed]
    public function activeSector(): ?Sector
    {
        return app(OperationalContext::class)->activeSector(auth()->user(), session());
    }

    #[Computed]
    public function unitWaitingCount(): int
    {
        $unit = $this->activeUnit;
        $desk = $this->activeDesk;

        if ($unit === null) {
            return 0;
        }

        return Ticket::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('status', TicketStatus::WAITING)
            ->when(
                $desk?->sector_id !== null,
                function ($query) use ($desk): void {
                    $query->where('sector_id', $desk->sector_id);
                },
            )
            ->count();
    }

    #[Computed]
    public function deskAvailableCount(): int
    {
        $unit = $this->activeUnit;
        $desk = $this->activeDesk;

        if ($unit === null || $desk === null) {
            return 0;
        }

        return Ticket::query()
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
            ->count();
    }

    /**
     * Hint when this unit is empty but other clinic units still have WAITING tickets.
     * Helps operators distinguish wrong-unit context from a true empty queue — without
     * exposing tickets from other clinics or breaking unit isolation.
     */
    #[Computed]
    public function crossUnitWaitingHint(): ?string
    {
        $unit = $this->activeUnit;

        if ($unit === null || $this->unitWaitingCount > 0) {
            return null;
        }

        $otherUnits = Unit::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('active', true)
            ->whereKeyNot($unit->id)
            ->whereHas('tickets', function ($query) use ($unit): void {
                $query->where('clinic_id', $unit->clinic_id)
                    ->where('status', TicketStatus::WAITING);
            })
            ->orderBy('name')
            ->pluck('name');

        if ($otherUnits->isEmpty()) {
            $kioskUnits = Kiosk::query()
                ->where('clinic_id', $unit->clinic_id)
                ->where('active', true)
                ->with('unit:id,name')
                ->get()
                ->pluck('unit.name')
                ->filter()
                ->unique()
                ->values();

            if ($kioskUnits->isEmpty()) {
                return null;
            }

            return 'Totens ativos desta clínica emitem para: '.$kioskUnits->implode(', ').'.';
        }

        return 'Há senhas aguardando em outras unidades ('.$otherUnits->implode(', ').'). Esta mesa atende somente '.$unit->name.'.';
    }

    /**
     * Contagens por tipo apenas das senhas disponíveis para a mesa atual.
     *
     * @return Collection<string, int>
     */
    #[Computed]
    public function queueCountsByType(): Collection
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
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'prefix', 'priority']);
    }

    /**
     * Últimas chamadas (INITIAL/RECALL) — preview fixo de 5, sem paginação.
     *
     * @return Collection<int, array{call_id: int, kind: string, at: CarbonImmutable, ticket_code: string, type_name: ?string, desk_label: string, event_label: string, status_label: ?string}>
     */
    #[Computed]
    public function recentHistory(): Collection
    {
        $unit = $this->activeUnit;

        if ($unit === null) {
            return collect();
        }

        return TicketCall::query()
            ->with([
                'ticket.ticketType:id,name,prefix',
                'desk:id,name,code',
            ])
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (TicketCall $call): array => [
                'call_id' => $call->id,
                'kind' => 'call',
                'at' => CarbonImmutable::parse($call->called_at)->timezone(config('app.timezone')),
                'ticket_code' => $call->ticket?->display_code ?? '—',
                'type_name' => $call->ticket?->ticketType?->name,
                'desk_label' => $call->desk?->name ?? '—',
                'event_label' => $call->call_type->label(),
                'status_label' => $call->ticket?->status?->label(),
            ])
            ->values();
    }

    public function deskState(): string
    {
        $ticket = $this->currentTicket;

        if ($ticket === null) {
            return 'free';
        }

        return $ticket->status === TicketStatus::IN_SERVICE ? 'in_service' : 'calling';
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.attendant-panel', [
            'now' => CarbonImmutable::now(config('app.timezone')),
            'selector' => app(NextTicketSelector::class),
            'canCall' => $user?->hasPermission('tickets.call') ?? false,
            'canRecall' => $user?->hasPermission('tickets.recall') ?? false,
            'canStart' => $user?->hasPermission('tickets.start') ?? false,
            'canComplete' => $user?->hasPermission('tickets.complete') ?? false,
            'canNoShow' => $user?->hasPermission('tickets.no_show') ?? false,
            'canTransfer' => $user?->hasPermission('tickets.transfer') ?? false,
        ]);
    }

    private function clearMessages(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
    }

    private function forgetComputed(): void
    {
        unset(
            $this->activeUnit,
            $this->activeDesk,
            $this->activeSector,
            $this->availableDesks,
            $this->deskSelectionCards,
            $this->transferDestinationDesks,
            $this->currentTicket,
            $this->upcomingQueue,
            $this->unitWaitingCount,
            $this->deskAvailableCount,
            $this->crossUnitWaitingHint,
            $this->queueCountsByType,
            $this->queueTicketTypes,
            $this->recentHistory,
            $this->operableUnits,
        );
    }
}
