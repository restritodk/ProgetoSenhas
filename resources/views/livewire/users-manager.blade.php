<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif

    @error('status')
        <x-ui.alert type="danger" class="mb-4">{{ $message }}</x-ui.alert>
    @enderror

    <x-ui.card title="Usuários" description="Gerencie o acesso da equipe à sua clínica e às unidades autorizadas.">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-3 md:grid-cols-3">
                <x-ui.input label="Buscar" name="user_search" id="user_search" wire:model.live.debounce.400ms="search" placeholder="Nome ou e-mail" />
                <x-ui.select label="Perfil" name="user_role_filter" id="user_role_filter" wire:model.live="roleFilter">
                    <option value="">Todos os perfis</option>
                    @foreach ($roles as $availableRole)
                        <option value="{{ $availableRole->value }}">{{ $availableRole->label() }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.select label="Status" name="user_status_filter" id="user_status_filter" wire:model.live="statusFilter">
                    <option value="">Todos os status</option>
                    <option value="active">Ativos</option>
                    <option value="inactive">Inativos</option>
                </x-ui.select>
            </div>
            <x-ui.button wire:click="startCreate" wire:loading.attr="disabled">
                <x-admin.icon name="plus" class="size-4" />
                Novo usuário
            </x-ui.button>
        </div>

        @if ($showForm)
            <form wire:submit="save" class="mb-6 grid gap-4 rounded-xl border border-border bg-background p-4 md:grid-cols-2">
                <x-ui.input label="Nome" name="user_name" id="user_name" wire:model="name" required maxlength="255" autocomplete="name">
                    <x-input-error :messages="$errors->get('name')" />
                </x-ui.input>
                <x-ui.input label="E-mail" name="user_email" id="user_email" type="email" wire:model="email" required maxlength="255" autocomplete="off">
                    <x-input-error :messages="$errors->get('email')" />
                </x-ui.input>
                <div>
                    <x-ui.select label="Perfil" name="user_role" id="user_role" wire:model="role" required>
                        @foreach ($roles as $availableRole)
                            <option value="{{ $availableRole->value }}">{{ $availableRole->label() }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-input-error :messages="$errors->get('role')" />
                </div>
                <div class="flex items-end">
                    <label class="flex min-h-11 items-center gap-3 text-sm text-text">
                        <input type="checkbox" wire:model="active" class="size-4 rounded border-border text-accent">
                        Conta ativa
                    </label>
                </div>
                <x-ui.input label="{{ $editingUserId ? 'Nova senha' : 'Senha inicial' }}" name="user_password" id="user_password" type="password" wire:model="password" autocomplete="new-password" :required="$editingUserId === null">
                    <p class="mt-1 text-xs text-text-muted">{{ $editingUserId ? 'Deixe em branco para manter a senha atual.' : 'Mínimo de 8 caracteres.' }}</p>
                    <x-input-error :messages="$errors->get('password')" />
                </x-ui.input>
                <x-ui.input label="Confirmar senha" name="user_password_confirmation" id="user_password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" :required="$editingUserId === null">
                    <x-input-error :messages="$errors->get('password_confirmation')" />
                </x-ui.input>

                <fieldset class="md:col-span-2">
                    <legend class="mb-2 text-sm font-medium text-text">Unidades autorizadas</legend>
                    <p class="mb-3 text-xs text-text-muted">
                        O vínculo operacional é opcional e independente da autorização administrativa. Administradores não recebem unidades automaticamente.
                    </p>
                    @if ($this->availableUnits->isEmpty())
                        <p class="text-sm text-text-muted">Nenhuma unidade cadastrada nesta clínica.</p>
                    @else
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($this->availableUnits as $unit)
                                <label wire:key="unit-option-{{ $unit->id }}" class="flex min-h-11 items-center gap-3 rounded-xl border border-border bg-surface px-3 text-sm">
                                    <input type="checkbox" value="{{ $unit->id }}" wire:model="unitIds" class="size-4 rounded border-border text-accent">
                                    <span>{{ $unit->name }}@unless ($unit->active) <span class="text-text-muted">(desativada)</span>@endunless</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <x-input-error :messages="$errors->get('unitIds')" />
                    <x-input-error :messages="$errors->get('unitIds.*')" />
                </fieldset>

                <div class="flex flex-wrap gap-3 md:col-span-2">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $editingUserId ? 'Salvar usuário' : 'Criar usuário' }}</span>
                        <span wire:loading wire:target="save">Salvando...</span>
                    </x-ui.button>
                    <x-ui.button variant="secondary" wire:click="cancel">Cancelar</x-ui.button>
                </div>
            </form>
        @endif

        @if ($users->isEmpty() && ! $showForm)
            <x-ui.empty-state
                title="{{ $search !== '' || $roleFilter !== '' || $statusFilter !== '' ? 'Nenhum usuário encontrado.' : 'Nenhum usuário cadastrado.' }}"
                description="{{ $search !== '' || $roleFilter !== '' || $statusFilter !== '' ? 'Ajuste a busca ou os filtros para tentar novamente.' : 'Cadastre o primeiro usuário da clínica.' }}"
            >
                @if ($search === '' && $roleFilter === '' && $statusFilter === '')
                    <x-ui.button wire:click="startCreate">Novo usuário</x-ui.button>
                @endif
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <caption class="sr-only">Usuários da clínica</caption>
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Nome</th>
                            <th scope="col" class="px-3 py-3 font-semibold">E-mail</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Perfil</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Unidades</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr wire:key="user-{{ $user->id }}" class="border-b border-border/70">
                                <td class="px-3 py-3 font-medium text-text">
                                    {{ $user->name }}
                                    @if ($user->is(auth()->user()))
                                        <x-ui.badge>Você</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-text-muted">{{ $user->email }}</td>
                                <td class="px-3 py-3">
                                    <x-ui.badge :tone="$user->isAdministrator() ? 'accent' : 'neutral'">{{ $user->role->label() }}</x-ui.badge>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($user->active)
                                        <x-ui.badge tone="success">Ativo</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="warning">Inativo</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-text-muted">
                                    {{ $user->units->isEmpty() ? 'Nenhuma' : $user->units->pluck('name')->join(', ') }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button variant="secondary" wire:click="edit({{ $user->id }})">Editar</x-ui.button>
                                        @if ($user->active)
                                            @php
                                                $isLastActiveAdministrator = $user->isAdministrator() && $activeAdministratorCount === 1;
                                            @endphp
                                            <x-ui.button
                                                variant="danger"
                                                wire:click="confirmDeactivation({{ $user->id }})"
                                                :disabled="$isLastActiveAdministrator"
                                            >
                                                Desativar
                                            </x-ui.button>
                                        @else
                                            <x-ui.button variant="secondary" wire:click="activate({{ $user->id }})">Ativar</x-ui.button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $users->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal title="Desativar usuário" :open="$userPendingDeactivationId !== null">
        <p>A conta permanecerá no histórico e poderá ser reativada depois. O usuário perderá o acesso operacional na próxima requisição protegida.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDeactivation">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="deactivate" wire:loading.attr="disabled">Desativar</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
