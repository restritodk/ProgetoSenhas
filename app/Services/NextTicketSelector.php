<?php

namespace App\Services;

use App\Models\Desk;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\UnitQueuePolicy;
use App\Models\UnitQueuePolicyProgress;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NextTicketSelector
{
    /**
     * Waiting bonuses: each full interval of waiting adds this many priority points.
     * Kept as fallback defaults when no UnitQueuePolicy is persisted.
     * Normal (10) reaches Preferencial (20) after 2 minutes and Emergencial (30) after 4 minutes
     * with the default 60s / +5 configuration.
     */
    public const int AGING_INTERVAL_SECONDS = 60;

    public const int AGING_BONUS_PER_INTERVAL = 5;

    /**
     * Large boost applied when a ticket exceeds its configured rescue wait.
     * Ensures rescued tickets win among non-critical candidates without claiming a hard SLA.
     */
    public const int RESCUE_PRIORITY_BOOST = 10_000;

    public function __construct(
        private UnitQueuePolicyResolver $policyResolver,
    ) {}

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
        $at ??= CarbonImmutable::now(config('app.timezone'));
        $policy = $this->policyResolver->findForUnit($unit);
        $progress = $policy !== null
            ? $this->policyResolver->progressForDisplay($policy)
            : null;

        $tickets = $this->waitingQuery($unit, $desk)
            ->with(['ticketType:id,clinic_id,name,prefix,priority,active'])
            ->orderBy('queued_at')
            ->orderBy('id')
            ->get();

        if ($policy === null || $progress === null) {
            return $this->rank($tickets, $at);
        }

        $selected = $this->selectFromLocked($tickets, $policy, $progress, $at);

        if ($selected === null) {
            return collect();
        }

        $rest = $tickets->reject(fn (Ticket $ticket): bool => $ticket->id === $selected->id)->values();

        return collect([$selected])->concat(
            $this->rank($rest, $at, $policy),
        )->values();
    }

    /**
     * Deterministic selection used inside CallNextTicket after WAITING rows are locked.
     * Updates nothing — CallNextTicket records progress after claiming the ticket.
     *
     * @param  Collection<int, Ticket>  $tickets
     */
    public function selectFromLocked(
        Collection $tickets,
        UnitQueuePolicy $policy,
        UnitQueuePolicyProgress $progress,
        ?CarbonImmutable $at = null,
    ): ?Ticket {
        $at ??= CarbonImmutable::now(config('app.timezone'));

        if ($tickets->isEmpty()) {
            return null;
        }

        if ($policy->usesAlwaysFirstCritical()) {
            $criticalId = (int) $policy->critical_ticket_type_id;
            $critical = $tickets
                ->filter(fn (Ticket $ticket): bool => (int) $ticket->ticket_type_id === $criticalId)
                ->sort(fn (Ticket $left, Ticket $right): int => $this->fifoCompare($left, $right))
                ->first();

            if ($critical !== null) {
                return $critical;
            }
        }

        if ($policy->anti_starvation_enabled) {
            $rescued = $this->rescuedTickets($tickets, $policy, $at);

            if ($rescued->isNotEmpty()) {
                return $this->rank($rescued, $at, $policy)->first();
            }
        }

        if ($policy->isDistributionConfigured()) {
            $sourceId = (int) $policy->distribution_source_ticket_type_id;
            $targetId = (int) $policy->distribution_target_ticket_type_id;
            $hasSource = $tickets->contains(fn (Ticket $ticket): bool => (int) $ticket->ticket_type_id === $sourceId);
            $hasTarget = $tickets->contains(fn (Ticket $ticket): bool => (int) $ticket->ticket_type_id === $targetId);

            if ($hasSource && $hasTarget) {
                $preferredId = $progress->isDueForTarget($policy) ? $targetId : $sourceId;

                $subset = $tickets->filter(function (Ticket $ticket) use ($preferredId, $sourceId, $targetId): bool {
                    $typeId = (int) $ticket->ticket_type_id;

                    return $typeId === $preferredId
                        || ($typeId !== $sourceId && $typeId !== $targetId);
                });

                if ($subset->isNotEmpty()) {
                    return $this->rank($subset, $at, $policy)->first();
                }
            }
        }

        return $this->rank($tickets, $at, $policy)->first();
    }

    /**
     * Rank an already-loaded collection with priority/aging rules.
     *
     * @param  Collection<int, Ticket>  $tickets
     * @return Collection<int, Ticket>
     */
    public function rank(
        Collection $tickets,
        ?CarbonImmutable $at = null,
        ?UnitQueuePolicy $policy = null,
    ): Collection {
        $at ??= CarbonImmutable::now(config('app.timezone'));

        return $tickets
            ->sort(function (Ticket $left, Ticket $right) use ($at, $policy): int {
                $priorityComparison = $this->effectivePriority($right, $at, $policy)
                    <=> $this->effectivePriority($left, $at, $policy);

                if ($priorityComparison !== 0) {
                    return $priorityComparison;
                }

                return $this->fifoCompare($left, $right);
            })
            ->values();
    }

    public function effectivePriority(
        Ticket $ticket,
        ?CarbonImmutable $at = null,
        ?UnitQueuePolicy $policy = null,
    ): int {
        $at ??= CarbonImmutable::now(config('app.timezone'));
        $interval = $policy?->aging_interval_seconds ?? self::AGING_INTERVAL_SECONDS;
        $bonus = $policy?->aging_bonus_per_interval ?? self::AGING_BONUS_PER_INTERVAL;
        $interval = max(1, (int) $interval);
        $bonus = max(0, (int) $bonus);

        $basePriority = (int) ($ticket->ticketType?->priority ?? 0);
        $queuedAt = $ticket->queued_at ?? $ticket->issued_at;
        $waitingSeconds = max(0, $queuedAt->diffInSeconds($at));
        $intervals = intdiv($waitingSeconds, $interval);
        $priority = $basePriority + ($intervals * $bonus);

        if ($policy?->anti_starvation_enabled) {
            $rescueMap = $policy->rescueWaitByTicketTypeId();
            $rescueSeconds = $rescueMap[(int) $ticket->ticket_type_id] ?? null;

            if ($rescueSeconds !== null && $waitingSeconds >= (int) $rescueSeconds) {
                $priority += self::RESCUE_PRIORITY_BOOST;
            }
        }

        return $priority;
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return Collection<int, Ticket>
     */
    private function rescuedTickets(Collection $tickets, UnitQueuePolicy $policy, CarbonImmutable $at): Collection
    {
        $rescueMap = $policy->rescueWaitByTicketTypeId();

        return $tickets->filter(function (Ticket $ticket) use ($rescueMap, $at): bool {
            $limit = $rescueMap[(int) $ticket->ticket_type_id] ?? null;

            if ($limit === null) {
                return false;
            }

            $queuedAt = $ticket->queued_at ?? $ticket->issued_at;
            $waitingSeconds = max(0, $queuedAt->diffInSeconds($at));

            return $waitingSeconds >= (int) $limit;
        })->values();
    }

    private function fifoCompare(Ticket $left, Ticket $right): int
    {
        $queuedComparison = $left->queued_at <=> $right->queued_at;

        if ($queuedComparison !== 0) {
            return $queuedComparison;
        }

        return $left->id <=> $right->id;
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

            if ($desk->sector_id !== null) {
                $query->where('sector_id', $desk->sector_id);
            }
        }

        return $query;
    }
}
