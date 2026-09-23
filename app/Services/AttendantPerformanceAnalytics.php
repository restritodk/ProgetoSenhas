<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\TicketStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendantPerformanceAnalytics
{
    /**
     * @return array{
     *     period: array{key: string, label: string, from: string, to: string},
     *     cards: list<array{key: string, label: string, value: int|float|null, previous: int|float|null, delta_percent: float|null, format: string}>,
     *     series: array<string, int>,
     *     by_type: list<array{name: string, count: int, color: string}>,
     *     avg_service_seconds: float|null,
     *     avg_wait_seconds: float|null,
     *     previous: array{completed: int, no_shows: int, avg_service_seconds: float|null, avg_wait_seconds: float|null},
     *     evolution: list<array{label: string, detail: string, tone: string}>
     * }
     */
    public function forAuthenticatedUser(
        string $period = 'today',
        ?string $customFrom = null,
        ?string $customTo = null,
    ): array {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->clinic_id !== null, 403);

        [$from, $to, $label] = $this->resolvePeriod($period, $customFrom, $customTo);
        $durationSeconds = max(1, $from->diffInSeconds($to));
        $previousTo = $from->subSecond();
        $previousFrom = $previousTo->subSeconds($durationSeconds - 1);

        $current = $this->aggregateWindow($user, $from, $to);
        $previous = $this->aggregateWindow($user, $previousFrom, $previousTo);

        $cards = [
            $this->card('completed', 'Concluídos', $current['completed'], $previous['completed'], 'int'),
            $this->card('no_shows', 'Não comparecimentos', $current['no_shows'], $previous['no_shows'], 'int'),
            $this->card(
                'avg_service',
                'Tempo médio de atendimento',
                $current['avg_service_seconds'],
                $previous['avg_service_seconds'],
                'duration',
            ),
            $this->card(
                'avg_wait',
                'Tempo médio de espera',
                $current['avg_wait_seconds'],
                $previous['avg_wait_seconds'],
                'duration',
            ),
        ];

        return [
            'period' => [
                'key' => $period,
                'label' => $label,
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'cards' => $cards,
            'series' => $current['series'],
            'by_type' => $current['by_type'],
            'avg_service_seconds' => $current['avg_service_seconds'],
            'avg_wait_seconds' => $current['avg_wait_seconds'],
            'previous' => [
                'completed' => $previous['completed'],
                'no_shows' => $previous['no_shows'],
                'avg_service_seconds' => $previous['avg_service_seconds'],
                'avg_wait_seconds' => $previous['avg_wait_seconds'],
            ],
            'evolution' => $this->buildEvolution($cards),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function resolvePeriod(string $period, ?string $customFrom, ?string $customTo): array
    {
        $tz = config('app.timezone');
        $now = CarbonImmutable::now($tz);

        return match ($period) {
            'today' => [$now->startOfDay(), $now->endOfDay(), 'Hoje'],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay(), 'Últimos 7 dias'],
            '30d' => [$now->subDays(29)->startOfDay(), $now->endOfDay(), 'Últimos 30 dias'],
            'month' => [$now->startOfMonth(), $now->endOfDay(), 'Este mês'],
            'year' => [$now->startOfYear(), $now->endOfDay(), 'Este ano'],
            'custom' => $this->resolveCustomPeriod($customFrom, $customTo, $tz),
            default => throw ValidationException::withMessages([
                'period' => 'Período inválido.',
            ]),
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function resolveCustomPeriod(?string $customFrom, ?string $customTo, string $tz): array
    {
        if ($customFrom === null || $customTo === null || $customFrom === '' || $customTo === '') {
            throw ValidationException::withMessages([
                'customFrom' => 'Informe o intervalo personalizado.',
            ]);
        }

        try {
            $from = CarbonImmutable::parse($customFrom, $tz)->startOfDay();
            $to = CarbonImmutable::parse($customTo, $tz)->endOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'customFrom' => 'Datas inválidas.',
            ]);
        }

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages([
                'customFrom' => 'A data inicial deve ser anterior à final.',
            ]);
        }

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages([
                'customFrom' => 'O intervalo personalizado pode ter no máximo 366 dias.',
            ]);
        }

        return [$from, $to, 'Personalizado'];
    }

    /**
     * @return array{
     *     completed: int,
     *     no_shows: int,
     *     avg_service_seconds: float|null,
     *     avg_wait_seconds: float|null,
     *     series: array<string, int>,
     *     by_type: list<array{name: string, count: int, color: string}>
     * }
     */
    private function aggregateWindow(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $clinicId = (int) $user->clinic_id;
        $userId = (int) $user->id;

        $completed = (int) Ticket::query()
            ->where('clinic_id', $clinicId)
            ->where('completed_by_user_id', $userId)
            ->where('status', TicketStatus::COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->count();

        $noShows = (int) Ticket::query()
            ->where('clinic_id', $clinicId)
            ->where('no_show_by_user_id', $userId)
            ->where('status', TicketStatus::NO_SHOW)
            ->whereBetween('completed_at', [$from, $to])
            ->count();

        $avgService = $this->averageServiceSeconds($clinicId, $userId, $from, $to);
        $avgWait = $this->averageWaitSeconds($clinicId, $userId, $from, $to);

        return [
            'completed' => $completed,
            'no_shows' => $noShows,
            'avg_service_seconds' => $avgService,
            'avg_wait_seconds' => $avgWait,
            'series' => $this->completionSeries($clinicId, $userId, $from, $to),
            'by_type' => $this->completionsByType($clinicId, $userId, $from, $to),
        ];
    }

    private function averageServiceSeconds(int $clinicId, int $userId, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $expr = $this->secondsBetweenExpr('service_started_at', 'completed_at');

        $value = Ticket::query()
            ->where('clinic_id', $clinicId)
            ->where('completed_by_user_id', $userId)
            ->where('status', TicketStatus::COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->whereNotNull('service_started_at')
            ->whereNotNull('completed_at')
            ->selectRaw("AVG({$expr}) as aggregate")
            ->value('aggregate');

        return $value === null ? null : round((float) $value, 1);
    }

    private function averageWaitSeconds(int $clinicId, int $userId, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $startExpr = $this->coalesceExpr(['service_started_at', 'called_at']);
        $queueExpr = $this->coalesceExpr(['queued_at', 'issued_at']);
        $expr = $this->secondsBetweenRawExpr($queueExpr, $startExpr);

        $value = Ticket::query()
            ->where('clinic_id', $clinicId)
            ->where('completed_by_user_id', $userId)
            ->where('status', TicketStatus::COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->whereRaw("{$startExpr} IS NOT NULL")
            ->whereRaw("{$queueExpr} IS NOT NULL")
            ->selectRaw("AVG({$expr}) as aggregate")
            ->value('aggregate');

        return $value === null ? null : round((float) $value, 1);
    }

    /**
     * @return array<string, int>
     */
    private function completionSeries(int $clinicId, int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dateExpr = $this->dateExpr('completed_at');

        /** @var Collection<int, object{bucket: string, aggregate: int|string}> $rows */
        $rows = Ticket::query()
            ->where('clinic_id', $clinicId)
            ->where('completed_by_user_id', $userId)
            ->where('status', TicketStatus::COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw("{$dateExpr} as bucket, COUNT(*) as aggregate")
            ->groupBy(DB::raw($dateExpr))
            ->orderBy('bucket')
            ->get();

        $series = [];
        $cursor = $from->startOfDay();
        $end = $to->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $series[$cursor->format('Y-m-d')] = 0;
            $cursor = $cursor->addDay();
        }

        foreach ($rows as $row) {
            $key = (string) $row->bucket;
            if (isset($series[$key])) {
                $series[$key] = (int) $row->aggregate;
            }
        }

        return $series;
    }

    /**
     * @return list<array{name: string, count: int, color: string}>
     */
    private function completionsByType(int $clinicId, int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var Collection<int, object{ticket_type_id: int, aggregate: int|string}> $rows */
        $rows = Ticket::query()
            ->where('clinic_id', $clinicId)
            ->where('completed_by_user_id', $userId)
            ->where('status', TicketStatus::COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('ticket_type_id, COUNT(*) as aggregate')
            ->groupBy('ticket_type_id')
            ->orderByDesc('aggregate')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $types = TicketType::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('id', $rows->pluck('ticket_type_id'))
            ->get(['id', 'name', 'prefix', 'priority'])
            ->keyBy('id');

        return $rows->map(function (object $row) use ($types): array {
            $type = $types->get((int) $row->ticket_type_id);

            return [
                'name' => $type?->name ?? 'Tipo #'.$row->ticket_type_id,
                'count' => (int) $row->aggregate,
                'color' => $this->colorForType($type),
            ];
        })->values()->all();
    }

    private function colorForType(?TicketType $type): string
    {
        if ($type === null) {
            return '#2563eb';
        }

        $haystack = mb_strtolower($type->name.' '.$type->prefix);

        if (str_contains($haystack, 'emerg') || str_contains($haystack, 'urgen')) {
            return '#dc2626';
        }

        if (str_contains($haystack, 'prefer') || str_contains($haystack, 'priorit')) {
            return '#d97706';
        }

        if (($type->priority ?? 0) >= 30) {
            return '#dc2626';
        }

        if (($type->priority ?? 0) >= 20) {
            return '#d97706';
        }

        return '#2563eb';
    }

    /**
     * @param  list<array{key: string, label: string, value: int|float|null, previous: int|float|null, delta_percent: float|null, format: string}>  $cards
     * @return list<array{label: string, detail: string, tone: string}>
     */
    private function buildEvolution(array $cards): array
    {
        $items = [];

        foreach ($cards as $card) {
            $delta = $card['delta_percent'];
            if ($delta === null) {
                $items[] = [
                    'label' => $card['label'],
                    'detail' => 'Sem base no período anterior para comparar.',
                    'tone' => 'neutral',
                ];

                continue;
            }

            $improved = match ($card['key']) {
                'completed' => $delta >= 0,
                'no_shows', 'avg_service', 'avg_wait' => $delta <= 0,
                default => $delta >= 0,
            };

            $direction = $delta > 0 ? 'aumentou' : ($delta < 0 ? 'reduziu' : 'manteve-se');
            $abs = abs($delta);

            $items[] = [
                'label' => $card['label'],
                'detail' => $direction === 'manteve-se'
                    ? 'Estável em relação ao período anterior.'
                    : sprintf('%s %.1f%% em relação ao período anterior.', ucfirst($direction), $abs),
                'tone' => $delta === 0.0 ? 'neutral' : ($improved ? 'positive' : 'attention'),
            ];
        }

        return $items;
    }

    /**
     * @return array{key: string, label: string, value: int|float|null, previous: int|float|null, delta_percent: float|null, format: string}
     */
    private function card(string $key, string $label, int|float|null $value, int|float|null $previous, string $format): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'previous' => $previous,
            'delta_percent' => $this->deltaPercent($value, $previous),
            'format' => $format,
        ];
    }

    private function deltaPercent(int|float|null $current, int|float|null $previous): ?float
    {
        if ($previous === null || (float) $previous === 0.0) {
            return $current === null || (float) $current === 0.0 ? null : 100.0;
        }

        if ($current === null) {
            return -100.0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
    }

    private function secondsBetweenExpr(string $startColumn, string $endColumn): string
    {
        return $this->secondsBetweenRawExpr($startColumn, $endColumn);
    }

    private function secondsBetweenRawExpr(string $startExpr, string $endExpr): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "(julianday({$endExpr}) - julianday({$startExpr})) * 86400.0",
            default => "TIMESTAMPDIFF(SECOND, {$startExpr}, {$endExpr})",
        };
    }

    /**
     * @param  list<string>  $columns
     */
    private function coalesceExpr(array $columns): string
    {
        return 'COALESCE('.implode(', ', $columns).')';
    }

    private function dateExpr(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "date({$column})",
            default => "DATE({$column})",
        };
    }
}
