<?php

namespace App\Services;

use App\Models\Desk;
use App\Models\Ticket;
use App\Models\Unit;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NextTicketSelector
{
    /**
     * Waiting bonuses: each full interval of waiting adds this many priority points.
     * Normal (10) reaches Preferencial (20) after 2 minutes and Emergencial (30) after 4 minutes.
     */
    public const int AGING_INTERVAL_SECONDS = 60;

    public const int AGING_BONUS_PER_INTERVAL = 5;

    /**
     * Side-effect free: identifies the next WAITING ticket for the unit without calling it.
     * When $desk is provided, only general-queue and desk-targeted tickets are candidates.
     */
    public function select(Unit $unit, ?CarbonImmutable $at = null, ?Desk $desk = null): ?Ticket
    {
        return $this->rankedWaitingQueue($unit, $at, $desk)->first();
    }

    /**
     * Ranked waiting queue for a unit. Does not mutate ticket status.
     *
     * @return Collection<int, Ticket>
     */
    public function rankedWaitingQueue(Unit $unit, ?CarbonImmutable $at = null, ?Desk $desk = null): Collection
    {
        $tickets = $this->waitingQuery($unit, $desk)
            ->with(['ticketType:id,clinic_id,name,prefix,priority,active'])
            ->orderBy('queued_at')
            ->orderBy('id')
            ->get();

        return $this->rank($tickets, $at);
    }

    /**
     * Rank an already-loaded collection with the same priority/aging rules.
     * Used by CallNextTicket after locking WAITING rows.
     *
     * @param  Collection<int, Ticket>  $tickets
     * @return Collection<int, Ticket>
     */
    public function rank(Collection $tickets, ?CarbonImmutable $at = null): Collection
    {
        $at ??= CarbonImmutable::now(config('app.timezone'));

        return $tickets
            ->sort(function (Ticket $left, Ticket $right) use ($at): int {
                $priorityComparison = $this->effectivePriority($right, $at)
                    <=> $this->effectivePriority($left, $at);

                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                $queuedComparison = $left->queued_at <=> $right->queued_at;

                if ($queuedComparison !== 0) {
                    return $queuedComparison;
                }

                return $left->id <=> $right->id;
            })
            ->values();
    }

    public function effectivePriority(Ticket $ticket, ?CarbonImmutable $at = null): int
    {
        $at ??= CarbonImmutable::now(config('app.timezone'));
        $basePriority = (int) ($ticket->ticketType?->priority ?? 0);
        $queuedAt = $ticket->queued_at ?? $ticket->issued_at;
        $waitingSeconds = max(0, $queuedAt->diffInSeconds($at));
        $intervals = intdiv($waitingSeconds, self::AGING_INTERVAL_SECONDS);

        return $basePriority + ($intervals * self::AGING_BONUS_PER_INTERVAL);
    }

    /**
     * @return Builder<Ticket>
     */
    private function waitingQuery(Unit $unit, ?Desk $desk = null): Builder
    {
        $query = Ticket::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('status', TicketStatus::WAITING);

        if ($desk !== null) {
            $query->where(function (Builder $builder) use ($desk): void {
                $builder->whereNull('target_desk_id')
                    ->orWhere('target_desk_id', $desk->id);
            });
        }

        return $query;
    }
}
