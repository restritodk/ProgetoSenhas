<div class="mx-auto max-w-3xl space-y-6">
    @if ($statusMessage !== '')
        <x-ui.alert type="success">{{ $statusMessage }}</x-ui.alert>
    @endif
    @if ($errorMessage !== '')
        <x-ui.alert type="danger">{{ $errorMessage }}</x-ui.alert>
    @endif

    <x-ui.card title="Foto de perfil" description="JPEG, PNG ou WEBP até 2 MB.">
        <div class="flex flex-wrap items-center gap-5">
            @if ($user?->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="" class="size-20 rounded-full object-cover ring-2 ring-border">
            @else
                <span class="flex size-20 items-center justify-center rounded-full bg-primary text-xl font-bold text-white">
                    {{ $user?->initials() }}
                </span>
            @endif
            <div class="space-y-3">
                <input
                    type="file"
                    wire:model="avatar"
                    accept="image/jpeg,image/png,image/webp"
                    class="block w-full text-sm text-text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-accent file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white"
                >
                <x-input-error :messages="$errors->get('avatar')" />
                <div class="flex flex-wrap gap-2">
                    <x-ui.button wire:click="saveAvatar" wire:loading.attr="disabled" wire:target="saveAvatar,avatar">
                        <span wire:loading.remove wire:target="saveAvatar">Salvar foto</span>
                        <span wire:loading wire:target="saveAvatar,avatar">Enviando...</span>
                    </x-ui.button>
                    @if ($user?->avatar_path)
                        <x-ui.button variant="secondary" wire:click="removeAvatar" wire:loading.attr="disabled">
                            Remover foto
                        </x-ui.button>
                    @endif
                </div>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Dados pessoais" description="Para alterar o e-mail, confirme com a senha atual.">
        <form wire:submit="saveIdentity" class="space-y-4">
            <x-ui.input label="Nome" name="profile_name" wire:model="name" required maxlength="120" />
            <x-input-error :messages="$errors->get('name')" />

            <x-ui.input label="E-mail" name="profile_email" type="email" wire:model="email" required />
            <x-input-error :messages="$errors->get('email')" />

            <x-ui.input
                label="Senha atual (obrigatória só se mudar o e-mail)"
                name="profile_current_password_identity"
                type="password"
                wire:model="current_password"
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('current_password')" />

            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveIdentity">
                Salvar dados
            </x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card title="Alterar senha" description="Use no mínimo 8 caracteres.">
        <form wire:submit="savePassword" class="space-y-4">
            <x-ui.input
                label="Senha atual"
                name="profile_current_password"
                type="password"
                wire:model="current_password"
                autocomplete="current-password"
                required
            />
            <x-input-error :messages="$errors->get('current_password')" />

            <x-ui.input
                label="Nova senha"
                name="profile_password"
                type="password"
                wire:model="password"
                autocomplete="new-password"
                required
            />
            <x-input-error :messages="$errors->get('password')" />

            <x-ui.input
                label="Confirmar nova senha"
                name="profile_password_confirmation"
                type="password"
                wire:model="password_confirmation"
                autocomplete="new-password"
                required
            />

            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="savePassword">
                Alterar senha
            </x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card title="Conta">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-text-muted">Perfil</dt>
                <dd class="mt-1 font-semibold text-text">{{ $user?->role?->label() }}</dd>
            </div>
            <div>
                <dt class="text-text-muted">Clínica</dt>
                <dd class="mt-1 font-semibold text-text">{{ $user?->clinic?->name ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui.card>
</div>
