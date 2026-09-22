<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Unit;
use App\TicketStatus;
use Carbon\CarbonImmutable;
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
     */
    public function select(Unit $unit, ?CarbonImmutable $at = null): ?Ticket
    {
        return $this->rankedWaitingQueue($unit, $at)->first();
    }

    /**
     * Ranked waiting queue for a unit. Does not mutate ticket status.
     *
     * @return Collection<int, Ticket>
     */
    public function rankedWaitingQueue(Unit $unit, ?CarbonImmutable $at = null): Collection
    {
        $tickets = Ticket::query()
            ->with(['ticketType:id,clinic_id,name,prefix,priority,active'])
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->where('status', TicketStatus::WAITING)
            ->orderBy('issued_at')
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

                $issuedComparison = $left->issued_at <=> $right->issued_at;

                if ($issuedComparison !== 0) {
                    return $issuedComparison;
                }

                return $left->id <=> $right->id;
            })
            ->values();
    }

    public function effectivePriority(Ticket $ticket, ?CarbonImmutable $at = null): int
    {
        $at ??= CarbonImmutable::now(config('app.timezone'));
        $basePriority = (int) ($ticket->ticketType?->priority ?? 0);
        $waitingSeconds = max(0, $ticket->issued_at->diffInSeconds($at));
        $intervals = intdiv($waitingSeconds, self::AGING_INTERVAL_SECONDS);

        return $basePriority + ($intervals * self::AGING_BONUS_PER_INTERVAL);
    }
}
