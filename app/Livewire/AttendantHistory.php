<?php

namespace App\Livewire;

use App\Models\Ticket;
use App\Models\TicketType;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class AttendantHistory extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public string $period = '30d';

    public string $customFrom = '';

    public string $customTo = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->canAccessAttendantPanel(), 403);

        $today = now(config('app.timezone'))->toDateString();
        $this->customFrom = now(config('app.timezone'))->subDays(29)->toDateString();
        $this->customTo = $today;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function applyCustomPeriod(): void
    {
        $this->period = 'custom';
        $this->resetPage();
    }

    /**
     * @return EloquentCollection<int, TicketType>
     */
    #[Computed]
    public function ticketTypes(): EloquentCollection
    {
        return TicketType::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->orderBy('name')
            ->get(['id', 'name', 'prefix']);
    }

    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function tickets(): LengthAwarePaginator
    {
        $user = auth()->user();
        [$from, $to] = $this->periodBounds();

        $query = Ticket::query()
            ->with([
                'ticketType:id,name,prefix',
                'currentDesk:id,name,code',
                'unit:id,name',
            ])
            ->where('clinic_id', $user?->clinic_id)
            ->where(function (Builder $builder) use ($user): void {
                $builder->where('called_by_user_id', $user?->id)
                    ->orWhere('started_by_user_id', $user?->id)
                    ->orWhere('completed_by_user_id', $user?->id)
                    ->orWhere('no_show_by_user_id', $user?->id);
            })
            ->where(function (Builder $builder) use ($from, $to): void {
                $builder->whereBetween('completed_at', [$from, $to])
                    ->orWhere(function (Builder $inner) use ($from, $to): void {
                        $inner->whereNull('completed_at')
                            ->whereBetween('called_at', [$from, $to]);
                    })
                    ->orWhere(function (Builder $inner) use ($from, $to): void {
                        $inner->whereNull('completed_at')
                            ->whereNull('called_at')
                            ->whereBetween('issued_at', [$from, $to]);
                    });
            })
            ->orderByRaw('COALESCE(completed_at, called_at, issued_at) DESC')
            ->orderByDesc('id');

        if ($this->statusFilter !== '') {
            $status = TicketStatus::tryFrom($this->statusFilter);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        if ($this->typeFilter !== '') {
            $query->where('ticket_type_id', (int) $this->typeFilter);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $digits = preg_replace('/\D+/', '', $search) ?: '';
            $query->where(function (Builder $builder) use ($search, $digits): void {
                $builder->whereHas('ticketType', function (Builder $typeQuery) use ($search): void {
                    $typeQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('prefix', 'like', '%'.$search.'%');
                });

                if ($digits !== '') {
                    $builder->orWhere('sequence_number', (int) $digits);
                }
            });
        }

        return $query->paginate(15);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodBounds(): array
    {
        $tz = config('app.timezone');
        $now = CarbonImmutable::now($tz);

        return match ($this->period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            'month' => [$now->startOfMonth(), $now->endOfDay()],
            'year' => [$now->startOfYear(), $now->endOfDay()],
            'custom' => [
                CarbonImmutable::parse($this->customFrom !== '' ? $this->customFrom : $now->toDateString(), $tz)->startOfDay(),
                CarbonImmutable::parse($this->customTo !== '' ? $this->customTo : $now->toDateString(), $tz)->endOfDay(),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
    }

    public function serviceDurationLabel(Ticket $ticket): string
    {
        if ($ticket->service_started_at === null || $ticket->completed_at === null) {
            return '—';
        }

        return $this->formatSeconds(
            max(0, (int) $ticket->service_started_at->diffInSeconds($ticket->completed_at))
        );
    }

    public function waitDurationLabel(Ticket $ticket): string
    {
        $start = $ticket->service_started_at ?? $ticket->called_at;
        $queued = $ticket->queued_at ?? $ticket->issued_at;

        if ($start === null || $queued === null) {
            return '—';
        }

        return $this->formatSeconds(max(0, (int) $queued->diffInSeconds($start)));
    }

    private function formatSeconds(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        if ($minutes >= 60) {
            return sprintf('%dh %02dmin', intdiv($minutes, 60), $minutes % 60);
        }

        return sprintf('%dmin %02ds', $minutes, $secs);
    }

    public function render(): View
    {
        return view('livewire.attendant-history', [
            'tickets' => $this->tickets(),
            'statuses' => [
                TicketStatus::COMPLETED,
                TicketStatus::NO_SHOW,
                TicketStatus::IN_SERVICE,
                TicketStatus::CALLED,
                TicketStatus::WAITING,
                TicketStatus::CANCELLED,
            ],
        ]);
    }
}
