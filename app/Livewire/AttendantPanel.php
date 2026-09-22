<?php

namespace App\Livewire;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\CompleteTicketService;
use App\Actions\MarkTicketNoShow;
use App\Actions\RecallTicket;
use App\Actions\ReleaseDesk;
use App\Actions\StartTicketService;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Services\NextTicketSelector;
use App\Services\OperationalContext;
use App\TicketStatus;
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
        $this->forgetComputed();
    }

    public function clearUnit(OperationalContext $operationalContext, ReleaseDesk $releaseDesk): void
    {
        $releaseDesk->handle(auth()->user());
        $operationalContext->clear(session());
        $this->clearMessages();
        $this->forgetComputed();
    }

    public function clearDesk(ReleaseDesk $releaseDesk): void
    {
        $releaseDesk->handle(auth()->user());
        $this->clearMessages();
        $this->statusMessage = 'Mesa liberada.';
        $this->forgetComputed();
    }

    public function selectDesk(int $deskId, ClaimDesk $claimDesk): void
    {
        $desk = Desk::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($deskId)
            ->firstOrFail();

        try {
            $claimDesk->handle(auth()->user(), $desk);
            $this->statusMessage = 'Mesa '.$desk->name.' ativada.';
            $this->errorMessage = '';
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível ativar a mesa.';
            $this->statusMessage = '';
        }

        $this->forgetComputed();
    }

    public function callNext(CallNextTicket $callNextTicket): void
    {
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

    public function noShow(MarkTicketNoShow $markTicketNoShow): void
    {
        $ticket = $this->currentTicket;
        abort_if($ticket === null, 404);

        try {
            $markTicketNoShow->handle(auth()->user(), $ticket);
            $this->statusMessage = 'Não comparecimento registrado.';
            $this->errorMessage = '';
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível registrar o não comparecimento.';
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
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('active', true)
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
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function upcomingQueue(): Collection
    {
        $unit = $this->activeUnit;

        if ($unit === null) {
            return collect();
        }

        return app(NextTicketSelector::class)
            ->rankedWaitingQueue($unit, CarbonImmutable::now(config('app.timezone')))
            ->take(5);
    }

    /**
     * @return Collection<string, int>
     */
    #[Computed]
    public function queueCountsByType(): Collection
    {
        $unit = $this->activeUnit;

        if ($unit === null) {
            return collect();
        }

        return Ticket::query()
            ->selectRaw('ticket_type_id, count(*) as aggregate')
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('status', TicketStatus::WAITING)
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
     * @return EloquentCollection<int, TicketCall>
     */
    #[Computed]
    public function recentCalls(): EloquentCollection
    {
        $unit = $this->activeUnit;

        if ($unit === null) {
            return new EloquentCollection;
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
            ->limit(8)
            ->get();
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
        return view('livewire.attendant-panel', [
            'now' => CarbonImmutable::now(config('app.timezone')),
            'selector' => app(NextTicketSelector::class),
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
            $this->availableDesks,
            $this->currentTicket,
            $this->upcomingQueue,
            $this->queueCountsByType,
            $this->queueTicketTypes,
            $this->recentCalls,
            $this->operableUnits,
        );
    }
}
