<div>
    @if ($errorMessage !== '')
        <x-ui.alert type="danger" class="mb-4">{{ $errorMessage }}</x-ui.alert>
    @endif

    @php
        $analytics = $this->analytics;
        $series = $analytics['series'] ?? [];
        $byType = $analytics['by_type'] ?? [];
        $seriesMax = max(1, ...(array_values($series) ?: [1]));
        $typeTotal = max(1, collect($byType)->sum('count'));
        $seriesPoints = [];
        $index = 0;
        $count = max(1, count($series));
        foreach ($series as $date => $value) {
            $x = $count === 1 ? 0 : ($index / ($count - 1)) * 100;
            $y = 100 - (($value / $seriesMax) * 88);
            $seriesPoints[] = ['x' => $x, 'y' => $y, 'value' => $value, 'date' => $date];
            $index++;
        }
        $polyline = collect($seriesPoints)->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ');
        $areaPath = 'M0,100 '.collect($seriesPoints)->map(fn ($p) => 'L'.$p['x'].','.$p['y'])->implode(' ').' L100,100 Z';
        $donutRadius = 42;
        $circumference = 2 * M_PI * $donutRadius;
        $donutOffset = 0;
    @endphp

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @foreach ([
                'today' => 'Hoje',
                '7d' => '7 dias',
                '30d' => '30 dias',
                'month' => 'Mês',
                'year' => 'Ano',
                'custom' => 'Personalizado',
            ] as $key => $label)
                <button
                    type="button"
                    wire:click="setPeriod('{{ $key }}')"
                    @class([
                        'inline-flex min-h-10 cursor-pointer items-center rounded-xl px-3 text-sm font-semibold transition duration-200',
                        'bg-primary text-white' => $period === $key,
                        'border border-border bg-surface text-text hover:bg-background' => $period !== $key,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($period === 'custom')
            <div class="flex flex-wrap items-end gap-2">
                <x-ui.input label="De" name="custom_from" type="date" wire:model="customFrom" />
                <x-ui.input label="Até" name="custom_to" type="date" wire:model="customTo" />
                <x-ui.button wire:click="applyCustomPeriod">Aplicar</x-ui.button>
            </div>
        @endif
    </div>

    <p class="mb-4 text-sm text-text-muted">Período: <strong class="text-text">{{ $analytics['period']['label'] ?? '' }}</strong></p>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($analytics['cards'] ?? [] as $card)
            <x-ui.card :title="$card['label']">
                <p class="text-3xl font-bold tabular-nums text-primary">
                    @if ($card['format'] === 'duration')
                        {{ $this->formatDuration($card['value']) }}
                    @else
                        {{ $card['value'] ?? 0 }}
                    @endif
                </p>
                @if ($card['delta_percent'] !== null)
                    @php
                        $positiveTone = in_array($card['key'], ['completed'], true)
                            ? $card['delta_percent'] >= 0
                            : $card['delta_percent'] <= 0;
                    @endphp
                    <p @class([
                        'mt-2 text-xs font-medium',
                        'text-success' => $positiveTone,
                        'text-warning' => ! $positiveTone,
                    ])>
                        {{ $card['delta_percent'] > 0 ? '+' : '' }}{{ number_format($card['delta_percent'], 1, ',', '.') }}% vs período anterior
                    </p>
                @else
                    <p class="mt-2 text-xs text-text-muted">Sem comparação disponível</p>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    <div class="mb-6 grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,0.8fr)]">
        <x-ui.card title="Evolução de concluídos" description="Atendimentos finalizados por você no período.">
            @if (empty($series) || collect($series)->sum() === 0)
                <x-ui.empty-state title="Sem concluídos" description="Ainda não há atendimentos finalizados neste período." />
            @else
                <div class="mt-2">
                    <svg viewBox="0 0 100 100" class="h-56 w-full overflow-visible" role="img" aria-label="Gráfico de área de concluídos">
                        <defs>
                            <linearGradient id="attendant-area" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#2563eb" stop-opacity="0.35" />
                                <stop offset="100%" stop-color="#2563eb" stop-opacity="0.02" />
                            </linearGradient>
                        </defs>
                        <path d="{{ $areaPath }}" fill="url(#attendant-area)" />
                        <polyline
                            fill="none"
                            stroke="#1e3a5f"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            points="{{ $polyline }}"
                        />
                    </svg>
                    <div class="mt-2 flex justify-between text-[11px] text-text-muted">
                        <span>{{ \Illuminate\Support\Carbon::parse(array_key_first($series))->format('d/m') }}</span>
                        <span>{{ \Illuminate\Support\Carbon::parse(array_key_last($series))->format('d/m') }}</span>
                    </div>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Por tipo de senha" description="Distribuição dos concluídos.">
            @if (empty($byType))
                <x-ui.empty-state title="Sem dados" description="Nenhuma conclusão por tipo neste período." />
            @else
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                    <svg viewBox="0 0 100 100" class="size-40 shrink-0" role="img" aria-label="Gráfico donut por tipo">
                        @foreach ($byType as $slice)
                            @php
                                $length = ($slice['count'] / $typeTotal) * $circumference;
                                $gap = $circumference - $length;
                            @endphp
                            <circle
                                cx="50"
                                cy="50"
                                r="{{ $donutRadius }}"
                                fill="transparent"
                                stroke="{{ $slice['color'] }}"
                                stroke-width="14"
                                stroke-dasharray="{{ $length }} {{ $gap }}"
                                stroke-dashoffset="{{ -$donutOffset }}"
                                transform="rotate(-90 50 50)"
                            />
                            @php $donutOffset += $length; @endphp
                        @endforeach
                        <circle cx="50" cy="50" r="28" fill="#ffffff" />
                        <text x="50" y="52" text-anchor="middle" class="fill-primary text-[12px] font-bold">{{ $typeTotal }}</text>
                    </svg>
                    <ul class="w-full space-y-2">
                        @foreach ($byType as $slice)
                            <li class="flex items-center justify-between gap-3 text-sm">
                                <span class="flex items-center gap-2 text-text">
                                    <span class="size-2.5 rounded-full" style="background: {{ $slice['color'] }}"></span>
                                    {{ $slice['name'] }}
                                </span>
                                <strong class="tabular-nums text-primary">{{ $slice['count'] }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-5 space-y-2">
                    @foreach ($byType as $slice)
                        @php $pct = ($slice['count'] / $typeTotal) * 100; @endphp
                        <div>
                            <div class="mb-1 flex justify-between text-xs text-text-muted">
                                <span>{{ $slice['name'] }}</span>
                                <span>{{ number_format($pct, 0) }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-background">
                                <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $slice['color'] }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Minha evolução" description="Comparação objetiva com o período anterior equivalente.">
        <ul class="space-y-3">
            @foreach ($analytics['evolution'] ?? [] as $item)
                <li @class([
                    'rounded-xl border px-4 py-3',
                    'border-emerald-200 bg-emerald-50' => $item['tone'] === 'positive',
                    'border-amber-200 bg-amber-50' => $item['tone'] === 'attention',
                    'border-border bg-background' => $item['tone'] === 'neutral',
                ])>
                    <p class="text-sm font-semibold text-text">{{ $item['label'] }}</p>
                    <p class="mt-1 text-sm text-text-muted">{{ $item['detail'] }}</p>
                </li>
            @endforeach
        </ul>
    </x-ui.card>
</div>
