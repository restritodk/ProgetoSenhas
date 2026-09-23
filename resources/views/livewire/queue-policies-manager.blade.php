<div>
    @if ($statusMessage !== '')
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => { show = false; $wire.clearStatusMessage() }, 4500)"
            x-show="show"
            x-transition.opacity
            role="status"
            aria-live="polite"
            class="mb-4"
        >
            <x-ui.alert type="success">✓ {{ $statusMessage }}</x-ui.alert>
        </div>
    @endif

    @if ($errorMessage !== '')
        <div class="mb-4" role="alert" aria-live="assertive">
            <x-ui.alert type="danger">{{ $errorMessage }}</x-ui.alert>
        </div>
    @endif

    <nav aria-label="Breadcrumb" class="mb-3 text-sm text-text-muted">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a href="{{ route('dashboard') }}" class="font-medium text-accent underline-offset-2 hover:underline">Início</a></li>
            <li aria-hidden="true">›</li>
            <li class="font-medium text-text">Filas e Prioridades</li>
        </ol>
    </nav>

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl">
            <h2 class="text-2xl font-semibold tracking-tight text-text">Filas e Prioridades</h2>
            <p class="mt-1 text-sm leading-6 text-text-muted">
                Configure as regras de atendimento e defina como as senhas serão chamadas.
            </p>
        </div>
        <x-ui.button variant="secondary" wire:click="openHelp" class="shrink-0">
            <x-admin.icon name="help" class="size-4" />
            Ajuda
        </x-ui.button>
    </div>

    @if ($this->units->count() > 1)
        <div class="mb-5 max-w-md">
            <x-ui.select label="Unidade" name="queue_policy_unit" id="queue_policy_unit" wire:model.live="selectedUnitId">
                @foreach ($this->units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </x-ui.select>
        </div>
    @elseif ($this->units->count() === 1)
        <p class="mb-5 text-sm text-text-muted">
            Unidade: <span class="font-medium text-text">{{ $this->units->first()->name }}</span>
        </p>
    @endif

    @if ($showHowItWorks)
        <div class="mb-6 flex gap-3 rounded-2xl border border-accent/20 bg-blue-50/80 px-4 py-4 sm:px-5">
            <div class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-accent/10 text-accent">
                <x-admin.icon name="info" class="size-4" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-primary">Como funciona?</p>
                <p class="mt-1 text-sm leading-6 text-text-muted">
                    As regras desta unidade controlam a <strong class="font-medium text-text">próxima chamada</strong> quando uma mesa fica disponível.
                    Senhas já em atendimento <strong class="font-medium text-text">não são interrompidas</strong>.
                    Se o tipo esperado pela distribuição não tiver ninguém aguardando, a mesa não fica parada — outra senha elegível é chamada.
                </p>
            </div>
            <button
                type="button"
                wire:click="dismissHowItWorks"
                class="shrink-0 rounded-lg px-2 py-1 text-sm font-medium text-text-muted hover:bg-white/70 hover:text-text"
                aria-label="Dispensar explicação"
            >
                Fechar
            </button>
        </div>
    @endif

    <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-border bg-surface px-4 py-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Tipos de senha</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-text">{{ $this->summary['ticket_types'] }}</p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <x-admin.icon name="ticket" class="size-5" />
                </span>
            </div>
        </div>
        <div class="rounded-2xl border border-border bg-surface px-4 py-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Tipos ativos</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-success">{{ $this->summary['active_types'] }}</p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-xl bg-success/10 text-success">
                    <x-admin.icon name="check" class="size-5" />
                </span>
            </div>
        </div>
        <div class="rounded-2xl border border-border bg-surface px-4 py-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Unidades com configuração</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-text">{{ $this->summary['units_with_policy'] }}</p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-xl bg-accent/10 text-accent">
                    <x-admin.icon name="building" class="size-5" />
                </span>
            </div>
        </div>
        <div class="rounded-2xl border border-border bg-surface px-4 py-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Mesas ativas</p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums text-text">{{ $this->summary['active_desks'] }}</p>
                    <p class="mt-1 text-xs text-text-muted">Guichês ativos nesta unidade</p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-xl bg-warning/10 text-warning">
                    <x-admin.icon name="desk" class="size-5" />
                </span>
            </div>
        </div>
    </div>

    <div class="mb-5 border-b border-border" role="tablist" aria-label="Seções de Filas e Prioridades">
        <div class="flex gap-1 overflow-x-auto">
            <button
                type="button"
                role="tab"
                aria-selected="{{ $activeTab === 'rules' ? 'true' : 'false' }}"
                wire:click="setTab('rules')"
                class="min-h-11 shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $activeTab === 'rules' ? 'border-accent text-accent' : 'border-transparent text-text-muted hover:text-text' }}"
            >
                Regras de Chamada
            </button>
            <button
                type="button"
                role="tab"
                aria-selected="{{ $activeTab === 'types' ? 'true' : 'false' }}"
                wire:click="setTab('types')"
                class="min-h-11 shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $activeTab === 'types' ? 'border-accent text-accent' : 'border-transparent text-text-muted hover:text-text' }}"
            >
                Tipos de Senha
            </button>
            <button
                type="button"
                role="tab"
                aria-selected="{{ $activeTab === 'advanced' ? 'true' : 'false' }}"
                wire:click="setTab('advanced')"
                class="min-h-11 shrink-0 border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $activeTab === 'advanced' ? 'border-accent text-accent' : 'border-transparent text-text-muted hover:text-text' }}"
            >
                Configurações Avançadas
            </button>
        </div>
    </div>

    @if ($activeTab === 'types')
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-text">Tipos de Senha</h3>
            <p class="mt-1 text-sm text-text-muted">
                Prioridade base e prefixos são gerenciados no módulo dedicado, para manter uma única fonte de verdade.
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ $ticketTypesUrl }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Gerenciar tipos de senha
                </a>
                <a href="{{ $unitTicketTypesUrl }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-border bg-surface px-4 py-2 text-sm font-semibold text-text hover:bg-background">
                    Tipos por unidade (Totem)
                </a>
            </div>
        </div>
    @elseif ($activeTab === 'advanced')
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6">
            <h3 class="text-base font-semibold text-text">Configurações Avançadas</h3>
            <p class="mt-1 text-sm leading-6 text-text-muted">
                O aging da fila (intervalo e bônus por espera) já é controlado em
                <strong class="font-medium text-text">Proteção contra espera excessiva</strong>
                na aba Regras de Chamada. Não há parâmetros avançados adicionais nesta versão —
                assim evitamos dois algoritmos conflitantes.
            </p>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-border bg-background px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Intervalo de aging</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-text">{{ $agingIntervalSeconds }}s</p>
                </div>
                <div class="rounded-xl border border-border bg-background px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Bônus por intervalo</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-text">+{{ $agingBonusPerInterval }}</p>
                </div>
            </div>
        </div>
    @else
        <form wire:submit="save" class="space-y-5">
            <div class="grid gap-5 lg:grid-cols-2">
                {{-- Prioridade Crítica --}}
                <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="critical-heading">
                    <div class="mb-4 flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-danger/10 text-danger">
                            <x-admin.icon name="alert" class="size-5" />
                        </span>
                        <div>
                            <h3 id="critical-heading" class="text-base font-semibold text-text">Prioridade Crítica</h3>
                            <p class="mt-0.5 text-sm text-text-muted">Defina o comportamento para senhas críticas.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <x-ui.select label="Tipo crítico" name="critical_ticket_type_id" id="critical_ticket_type_id" wire:model="criticalTicketTypeId">
                            <option value="">Nenhum</option>
                            @foreach ($this->activeTicketTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }} ({{ $type->prefix }})</option>
                            @endforeach
                        </x-ui.select>
                        <x-input-error :messages="$errors->get('critical_ticket_type_id')" />

                        <fieldset>
                            <legend class="mb-2 text-sm font-medium text-text">Comportamento</legend>
                            <div class="space-y-2">
                                @foreach ($criticalModes as $mode)
                                    <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border border-border bg-background px-3 py-3 has-[:checked]:border-accent/40 has-[:checked]:bg-blue-50/50">
                                        <input
                                            type="radio"
                                            wire:model="criticalMode"
                                            value="{{ $mode->value }}"
                                            class="mt-1 size-4 border-border text-accent"
                                        >
                                        <span class="text-sm text-text">{{ $mode->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('critical_mode')" />
                        </fieldset>

                        <p class="rounded-xl border border-border bg-background px-3 py-2.5 text-xs leading-5 text-text-muted">
                            Senhas em atendimento não são interrompidas.
                            A prioridade é aplicada à próxima chamada quando uma mesa ficar disponível.
                        </p>
                    </div>
                </section>

                {{-- Distribuição --}}
                <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="distribution-heading">
                    <div class="mb-4 flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent">
                            <x-admin.icon name="sort" class="size-5" />
                        </span>
                        <div>
                            <h3 id="distribution-heading" class="text-base font-semibold text-text">Distribuição de Chamadas</h3>
                            <p class="mt-0.5 text-sm text-text-muted">Equilibre o atendimento entre tipos de senha.</p>
                        </div>
                    </div>

                    <label class="mb-4 flex min-h-11 cursor-pointer items-center gap-3 text-sm text-text">
                        <input type="checkbox" wire:model.live="distributionEnabled" class="size-4 rounded border-border text-accent">
                        Habilitar regra de distribuição
                    </label>

                    <div @class(['space-y-3', 'pointer-events-none opacity-50' => $distributionEnabled === false])>
                        <p class="text-sm text-text-muted">Regra proporcional (compartilhada por todas as mesas da unidade):</p>
                        <div class="grid gap-3 rounded-xl border border-border bg-background p-3 sm:grid-cols-2 sm:p-4 lg:grid-cols-4">
                            <div>
                                <label for="distribution_source_count" class="mb-1.5 block text-sm font-medium text-text">A cada</label>
                                <input
                                    id="distribution_source_count"
                                    type="number"
                                    min="1"
                                    max="50"
                                    wire:model="distributionSourceCount"
                                    class="min-h-11 w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm"
                                    aria-describedby="distribution_source_count_hint"
                                >
                                <p id="distribution_source_count_hint" class="mt-1 text-xs text-text-muted">chamadas de</p>
                            </div>
                            <div>
                                <label for="distribution_source_ticket_type_id" class="mb-1.5 block text-sm font-medium text-text">Tipo origem</label>
                                <select
                                    id="distribution_source_ticket_type_id"
                                    wire:model="distributionSourceTicketTypeId"
                                    class="min-h-11 w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm"
                                >
                                    <option value="">Selecione</option>
                                    @foreach ($this->activeTicketTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="distribution_target_count" class="mb-1.5 block text-sm font-medium text-text">Chamar</label>
                                <input
                                    id="distribution_target_count"
                                    type="number"
                                    min="1"
                                    max="50"
                                    wire:model="distributionTargetCount"
                                    class="min-h-11 w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm"
                                    aria-describedby="distribution_target_count_hint"
                                >
                                <p id="distribution_target_count_hint" class="mt-1 text-xs text-text-muted">senha(s) de</p>
                            </div>
                            <div>
                                <label for="distribution_target_ticket_type_id" class="mb-1.5 block text-sm font-medium text-text">Tipo destino</label>
                                <select
                                    id="distribution_target_ticket_type_id"
                                    wire:model="distributionTargetTicketTypeId"
                                    class="min-h-11 w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm"
                                >
                                    <option value="">Selecione</option>
                                    @foreach ($this->activeTicketTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('distribution_source_count')" />
                        <x-input-error :messages="$errors->get('distribution_target_count')" />
                        <x-input-error :messages="$errors->get('distribution_source_ticket_type_id')" />
                        <x-input-error :messages="$errors->get('distribution_target_ticket_type_id')" />
                        <p class="text-xs leading-5 text-text-muted">
                            Exemplo: 3 Normais → 1 Preferencial. O progresso é da fila da unidade, não por mesa ou navegador.
                        </p>
                    </div>
                </section>

                {{-- Anti-starvation --}}
                <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="anti-starvation-heading">
                    <div class="mb-4 flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning">
                            <x-admin.icon name="clock" class="size-5" />
                        </span>
                        <div>
                            <h3 id="anti-starvation-heading" class="text-base font-semibold text-text">Proteção contra espera excessiva</h3>
                            <p class="mt-0.5 text-sm text-text-muted">Limite de espera para priorização (não é garantia de SLA).</p>
                        </div>
                    </div>

                    <label class="mb-4 flex min-h-11 cursor-pointer items-center gap-3 text-sm text-text">
                        <input type="checkbox" wire:model.live="antiStarvationEnabled" class="size-4 rounded border-border text-accent">
                        Habilitar proteção contra espera excessiva
                    </label>

                    <div @class(['space-y-4', 'pointer-events-none opacity-50' => $antiStarvationEnabled === false])>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.input
                                label="Intervalo de aging (segundos)"
                                name="aging_interval_seconds"
                                id="aging_interval_seconds"
                                type="number"
                                min="15"
                                max="3600"
                                wire:model="agingIntervalSeconds"
                            >
                                <p class="mt-1 text-xs text-text-muted">A cada intervalo, a senha ganha bônus de prioridade.</p>
                            </x-ui.input>
                            <x-ui.input
                                label="Bônus por intervalo"
                                name="aging_bonus_per_interval"
                                id="aging_bonus_per_interval"
                                type="number"
                                min="1"
                                max="100"
                                wire:model="agingBonusPerInterval"
                            />
                        </div>
                        <x-input-error :messages="$errors->get('aging_interval_seconds')" />
                        <x-input-error :messages="$errors->get('aging_bonus_per_interval')" />

                        <fieldset>
                            <legend class="mb-2 text-sm font-medium text-text">Limite de espera para priorização (minutos)</legend>
                            <div class="space-y-2">
                                @foreach ($this->activeTicketTypes as $type)
                                    <div class="flex flex-col gap-2 rounded-xl border border-border bg-background px-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-text">{{ $type->name }}</p>
                                            <p class="text-xs text-text-muted">Prefixo {{ $type->prefix }} · prioridade {{ $type->priority }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <label class="sr-only" for="rescue_wait_{{ $type->id }}">Minutos para {{ $type->name }}</label>
                                            <input
                                                id="rescue_wait_{{ $type->id }}"
                                                type="number"
                                                min="1"
                                                max="1440"
                                                wire:model="rescueWaitMinutes.{{ $type->id }}"
                                                class="w-24 rounded-xl border border-border bg-surface px-3 py-2 text-sm text-text"
                                            >
                                            <span class="text-sm text-text-muted">min</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs leading-5 text-text-muted">
                                Ao atingir o limite, a senha recebe prioridade de resgate na próxima seleção compatível.
                                Isso não garante atendimento no minuto exato.
                            </p>
                        </fieldset>
                    </div>
                </section>

                {{-- Ordem de Prioridade --}}
                <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6" aria-labelledby="order-heading">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <x-admin.icon name="lightning" class="size-5" />
                            </span>
                            <div>
                                <h3 id="order-heading" class="text-base font-semibold text-text">Ordem de Prioridade</h3>
                                <p class="mt-0.5 text-sm text-text-muted">Prioridade base dos tipos (somente leitura aqui).</p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-border">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-background text-xs font-semibold uppercase tracking-wide text-text-muted">
                                <tr>
                                    <th scope="col" class="px-3 py-2.5">Ordem</th>
                                    <th scope="col" class="px-3 py-2.5">Tipo de senha</th>
                                    <th scope="col" class="px-3 py-2.5">Prefixo</th>
                                    <th scope="col" class="px-3 py-2.5">Prioridade</th>
                                    <th scope="col" class="px-3 py-2.5">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->ticketTypes->sortByDesc('priority')->values() as $index => $type)
                                    <tr class="border-t border-border/80" wire:key="order-type-{{ $type->id }}">
                                        <td class="px-3 py-3 tabular-nums text-text-muted">{{ $index + 1 }}</td>
                                        <td class="px-3 py-3 font-medium text-text">{{ $type->name }}</td>
                                        <td class="px-3 py-3">
                                            <span class="inline-flex size-8 items-center justify-center rounded-full bg-accent/10 text-xs font-bold text-accent">{{ $type->prefix }}</span>
                                        </td>
                                        <td class="px-3 py-3 tabular-nums text-text">{{ $type->priority }}</td>
                                        <td class="px-3 py-3">
                                            @if ($type->active)
                                                <span class="inline-flex items-center gap-1.5 text-success">
                                                    <span class="size-2 rounded-full bg-success" aria-hidden="true"></span>
                                                    Ativo
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 text-text-muted">
                                                    <span class="size-2 rounded-full bg-text-muted" aria-hidden="true"></span>
                                                    Inativo
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3 py-6 text-center text-text-muted">Nenhum tipo cadastrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <a href="{{ $ticketTypesUrl }}" class="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-accent underline-offset-2 hover:underline">
                        Gerenciar tipos de senha →
                    </a>
                </section>
            </div>

            <div class="sticky bottom-0 z-10 -mx-4 border-t border-border bg-background/95 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border sm:bg-surface sm:px-5 sm:shadow-sm">
                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        wire:click="confirmRestore"
                        wire:loading.attr="disabled"
                        wire:target="save,restoreDefaults"
                    >
                        Restaurar padrão
                    </x-ui.button>
                    <x-ui.button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save,restoreDefaults"
                        :disabled="$saving || $selectedUnitId === null"
                    >
                        <span wire:loading.remove wire:target="save">Salvar configurações</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                            <span class="inline-block size-4 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true"></span>
                            Salvando…
                        </span>
                    </x-ui.button>
                </div>
            </div>
        </form>
    @endif

    <x-ui.modal title="Ajuda — Filas e Prioridades" :open="$showHelp" maxWidth="2xl" closeMethod="closeHelp" id="queue-help-title">
        <div class="space-y-4 text-sm leading-6 text-text-muted">
            <div>
                <p class="font-semibold text-text">Prioridade crítica</p>
                <p class="mt-1">Quando “Sempre chamar antes” está ativo, qualquer senha WAITING do tipo crítico escolhido é selecionada na próxima chamada disponível. Atendimentos em andamento não são interrompidos.</p>
            </div>
            <div>
                <p class="font-semibold text-text">Distribuição</p>
                <p class="mt-1">Com a regra 3 Normais → 1 Preferencial, o sistema tenta distribuir as chamadas nessa proporção quando ambos os tipos possuem pessoas aguardando. O contador é da fila da unidade e é compartilhado por todas as mesas.</p>
                <p class="mt-1">Se não houver senha do tipo esperado, a mesa não fica parada — outra senha elegível é chamada.</p>
            </div>
            <div>
                <p class="font-semibold text-text">Proteção contra espera excessiva</p>
                <p class="mt-1">Combina aging configurável (bônus a cada intervalo) com um limite de espera para priorização. Ao atingir o limite, a senha ganha prioridade de resgate. Isso não promete atendimento no minuto exato.</p>
            </div>
            <div>
                <p class="font-semibold text-text">Prioridade base</p>
                <p class="mt-1">O número de prioridade de cada tipo (maior = mais prioritário) é definido em Tipos de Senha e usado como base do cálculo.</p>
            </div>
        </div>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="closeHelp">Fechar</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal title="Restaurar configurações padrão?" :open="$showRestoreConfirm" closeMethod="cancelRestore" id="queue-restore-title">
        <p class="text-sm leading-6 text-text-muted">
            As regras personalizadas desta unidade serão substituídas pelos valores padrão da clínica.
        </p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelRestore" wire:loading.attr="disabled" wire:target="restoreDefaults">Cancelar</x-ui.button>
            <x-ui.button wire:click="restoreDefaults" wire:loading.attr="disabled" wire:target="restoreDefaults">
                <span wire:loading.remove wire:target="restoreDefaults">Sim, restaurar</span>
                <span wire:loading wire:target="restoreDefaults">Restaurando…</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
