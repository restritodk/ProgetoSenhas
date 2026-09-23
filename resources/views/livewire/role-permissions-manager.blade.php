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

    @if ($editingRole === null)
        <nav aria-label="Breadcrumb" class="mb-3 text-sm text-text-muted">
            <ol class="flex flex-wrap items-center gap-1.5">
                <li><a href="{{ route('dashboard') }}" class="font-medium text-accent underline-offset-2 hover:underline">Início</a></li>
                <li aria-hidden="true">›</li>
                <li class="font-medium text-text">Perfis e Permissões</li>
            </ol>
        </nav>

        <div class="mb-6 max-w-3xl">
            <h2 class="text-2xl font-semibold tracking-tight text-text">Perfis e Permissões</h2>
            <p class="mt-1 text-sm leading-6 text-text-muted">
                Gerencie os níveis de acesso e defina o que cada perfil pode visualizar e executar no sistema.
            </p>
        </div>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-border bg-surface px-4 py-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Perfis configurados</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-text">{{ $this->summary['profiles'] }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-surface px-4 py-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Usuários ativos</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-success">{{ $this->summary['active_users'] }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-surface px-4 py-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Atendentes</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-text">{{ $this->summary['attendants'] }}</p>
            </div>
            <div class="rounded-2xl border border-border bg-surface px-4 py-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Administradores</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-primary">{{ $this->summary['administrators'] }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            @foreach ($this->roleCards as $card)
                @php
                    $role = $card['role'];
                @endphp
                <article class="flex flex-col rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6" wire:key="role-card-{{ $role->value }}">
                    <div class="mb-4 flex items-start gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl {{ $role === \App\UserRole::ADMINISTRATOR ? 'bg-primary/10 text-primary' : ($role === \App\UserRole::SUPERVISOR ? 'bg-accent/10 text-accent' : 'bg-success/10 text-success') }}">
                            <x-admin.icon name="{{ $role === \App\UserRole::ADMINISTRATOR ? 'shield' : ($role === \App\UserRole::SUPERVISOR ? 'users' : 'heart') }}" class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-semibold text-text">{{ $role->label() }}</h3>
                                <x-ui.badge tone="neutral">Ativo</x-ui.badge>
                            </div>
                            <p class="mt-1 text-sm leading-6 text-text-muted">{{ $card['description'] }}</p>
                        </div>
                    </div>
                    <p class="text-sm text-text">
                        <span class="font-semibold tabular-nums">{{ $card['users_count'] }}</span>
                        {{ $card['users_count'] === 1 ? 'usuário' : 'usuários' }}
                    </p>
                    <p class="mt-1 text-xs text-text-muted">{{ $card['summary'] }}</p>
                    <div class="mt-auto flex flex-wrap gap-2 pt-5">
                        <x-ui.button wire:click="editRole('{{ $role->value }}')" class="w-full sm:w-auto">
                            Gerenciar permissões
                        </x-ui.button>
                        <a
                            href="{{ $usersUrl }}?role={{ $role->value }}"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-border bg-surface px-4 py-2 text-sm font-semibold text-text hover:bg-background sm:w-auto"
                        >
                            Ver usuários
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        @php
            $roleEnum = \App\UserRole::from($editingRole);
            $usersCount = $this->roleCards->firstWhere(fn ($card) => $card['role']->value === $editingRole)['users_count'] ?? 0;
            $enabledTotal = count($selectedPermissions);
        @endphp

        <nav aria-label="Breadcrumb" class="mb-3 text-sm text-text-muted">
            <ol class="flex flex-wrap items-center gap-1.5">
                <li>
                    <button type="button" wire:click="backToList" class="font-medium text-accent underline-offset-2 hover:underline">
                        ← Perfis e Permissões
                    </button>
                </li>
                <li aria-hidden="true">›</li>
                <li class="font-medium text-text">{{ $roleEnum->label() }}</li>
            </ol>
        </nav>

        <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-semibold tracking-tight text-text">{{ $roleEnum->label() }}</h2>
                    @if ($this->isDirty())
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning" role="status">
                            <span class="size-1.5 rounded-full bg-warning" aria-hidden="true"></span>
                            Alterações não salvas
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm leading-6 text-text-muted">
                    Defina quais recursos e operações este perfil pode utilizar.
                </p>
                <p class="mt-2 text-sm text-text">
                    <x-ui.badge tone="accent">{{ $usersCount }} {{ $usersCount === 1 ? 'usuário utilizando este perfil' : 'usuários utilizando este perfil' }}</x-ui.badge>
                    <span class="ml-2 text-text-muted">{{ $enabledTotal }} permissões habilitadas</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="secondary" wire:click="discardChanges" wire:loading.attr="disabled" :disabled="$this->isDirty() === false">
                    Descartar alterações
                </x-ui.button>
                @if ($canUpdate)
                    <x-ui.button wire:click="confirmSave" wire:loading.attr="disabled" :disabled="$this->isDirty() === false || $saving">
                        Salvar permissões
                    </x-ui.button>
                @endif
            </div>
        </div>

        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="flex-1">
                <label for="permission_search" class="mb-1.5 block text-sm font-medium text-text">Buscar permissão</label>
                <input
                    id="permission_search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar permissão..."
                    class="min-h-11 w-full rounded-xl border border-border bg-surface px-3 py-2 text-sm"
                >
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="secondary" wire:click="expandAll">Expandir todos</x-ui.button>
                <x-ui.button variant="secondary" wire:click="collapseAll">Recolher todos</x-ui.button>
            </div>
        </div>

        <div class="space-y-4 pb-24">
            @forelse ($groupedPermissions as $moduleKey => $permissions)
                @php
                    $meta = $modulesMeta[$moduleKey] ?? ['label' => $moduleKey, 'description' => ''];
                    $moduleKeys = $permissions->pluck('key')->all();
                    $enabledInModule = count(array_intersect($selectedPermissions, $moduleKeys));
                    $expanded = $expandedModules[$moduleKey] ?? true;
                @endphp
                <section class="rounded-2xl border border-border bg-surface shadow-sm" wire:key="module-{{ $moduleKey }}">
                    <div class="flex flex-col gap-3 border-b border-border px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <button type="button" class="flex min-w-0 flex-1 items-start gap-3 text-left" wire:click="toggleModule('{{ $moduleKey }}')" aria-expanded="{{ $expanded ? 'true' : 'false' }}">
                            <span class="mt-0.5 text-text-muted" aria-hidden="true">{{ $expanded ? '▾' : '▸' }}</span>
                            <span>
                                <span class="block text-base font-semibold text-text">{{ $meta['label'] }}</span>
                                <span class="mt-0.5 block text-sm text-text-muted">{{ $meta['description'] }}</span>
                            </span>
                        </button>
                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <span class="text-xs font-semibold uppercase tracking-wide text-text-muted">{{ $enabledInModule }} de {{ count($moduleKeys) }}</span>
                            @if ($canUpdate)
                                <button type="button" wire:click="selectModule('{{ $moduleKey }}')" class="min-h-10 rounded-lg px-3 text-sm font-medium text-accent hover:bg-accent/5">Selecionar todas</button>
                                <button type="button" wire:click="clearModule('{{ $moduleKey }}')" class="min-h-10 rounded-lg px-3 text-sm font-medium text-text-muted hover:bg-background">Limpar</button>
                            @endif
                        </div>
                    </div>

                    @if ($expanded)
                        <fieldset class="space-y-1 px-2 py-3 sm:px-3">
                            <legend class="sr-only">{{ $meta['label'] }}</legend>
                            @foreach ($permissions as $permission)
                                @php
                                    $permissionKey = $permission['key'];
                                    $inputId = 'permission-'.$editingRole.'-'.str_replace('.', '-', $permissionKey);
                                    $protected = $this->isProtected($permissionKey);
                                    $depends = $permission['depends_on'] ?? [];
                                    $isDisabled = $canUpdate === false || $protected;
                                @endphp
                                <div
                                    wire:key="permission-{{ $editingRole }}-{{ $permissionKey }}"
                                    class="rounded-xl hover:bg-background {{ $protected ? 'opacity-95' : '' }}"
                                >
                                    <label
                                        class="flex min-h-14 cursor-pointer items-start gap-3 px-3 py-3 {{ $isDisabled ? 'cursor-not-allowed' : '' }}"
                                    >
                                        <input
                                            id="{{ $inputId }}"
                                            type="checkbox"
                                            value="{{ $permissionKey }}"
                                            wire:model.live="selectedPermissions"
                                            @disabled($isDisabled)
                                            class="mt-1 size-4 rounded border-border text-accent disabled:cursor-not-allowed"
                                        >
                                        <span class="min-w-0">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-semibold text-text">{{ $permission['name'] }}</span>
                                                @if ($protected)
                                                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">Obrigatória para administradores</span>
                                                @endif
                                            </span>
                                            <span class="mt-0.5 block text-sm text-text-muted">{{ $permission['description'] }}</span>
                                            @if ($depends !== [])
                                                <span class="mt-1 block text-xs text-text-muted">
                                                    Depende de: {{ collect($depends)->map(fn ($key) => \App\Support\PermissionCatalog::keyed()[$key]['name'] ?? $key)->implode(', ') }}
                                                </span>
                                            @endif
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </fieldset>
                    @endif
                </section>
            @empty
                <div class="rounded-2xl border border-border bg-surface px-5 py-10 text-center text-sm text-text-muted">
                    Nenhuma permissão encontrada para “{{ $search }}”.
                </div>
            @endforelse
        </div>

        @if ($canUpdate)
            <div class="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-background/95 px-4 py-3 backdrop-blur sm:px-6 lg:px-8">
                <div class="mx-auto flex max-w-7xl flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <x-ui.button variant="secondary" wire:click="discardChanges" :disabled="$this->isDirty() === false || $saving">Descartar alterações</x-ui.button>
                    <x-ui.button wire:click="confirmSave" wire:loading.attr="disabled" :disabled="$this->isDirty() === false || $saving">
                        <span wire:loading.remove wire:target="save">Salvar permissões</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                            <span class="inline-block size-4 animate-spin rounded-full border-2 border-white/30 border-t-white" aria-hidden="true"></span>
                            Salvando…
                        </span>
                    </x-ui.button>
                </div>
            </div>
        @endif
    @endif

    <x-ui.modal title="Alterar permissões do perfil?" :open="$showSaveConfirm" closeMethod="cancelSave" id="role-save-title">
        @php
            $confirmUsersCount = $editingRole
                ? ($this->roleCards->firstWhere(fn ($card) => $card['role']->value === $editingRole)['users_count'] ?? 0)
                : 0;
        @endphp
        <p class="text-sm leading-6 text-text-muted">
            Essas alterações serão aplicadas aos
            <strong class="font-semibold text-text">{{ $confirmUsersCount }}</strong>
            usuários que utilizam este perfil.
        </p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelSave">Cancelar</x-ui.button>
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Salvar permissões</span>
                <span wire:loading wire:target="save">Salvando…</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal title="Desmarcar permissão principal?" :open="$showUncheckDependentsConfirm" closeMethod="cancelUncheckDependents" id="role-uncheck-title">
        <div class="space-y-3 text-sm leading-6 text-text-muted">
            <p>
                Outras permissões dependem de
                <strong class="font-semibold text-text">“{{ $this->pendingUncheckLabel() }}”</strong>
                e também precisarão ser desmarcadas.
            </p>
            @if ($this->pendingCascadeLabels() !== [])
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($this->pendingCascadeLabels() as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelUncheckDependents">Cancelar</x-ui.button>
            <x-ui.button wire:click="confirmUncheckDependents">Desmarcar permissões</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal title="Existem alterações não salvas" :open="$showDiscardConfirm" closeMethod="cancelDiscard" id="role-discard-title">
        <p class="text-sm leading-6 text-text-muted">
            Deseja descartar as alterações e continuar?
        </p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDiscard">Continuar editando</x-ui.button>
            <x-ui.button wire:click="confirmDiscard">Descartar alterações</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
