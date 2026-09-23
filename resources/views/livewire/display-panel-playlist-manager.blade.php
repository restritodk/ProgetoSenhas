<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif
    @if ($errorMessage !== '')
        <x-ui.alert type="danger" class="mb-4">{{ $errorMessage }}</x-ui.alert>
    @endif

    <x-ui.card title="Playlist do painel" description="{{ $this->panel->name }}">
        <div class="mb-4 grid gap-1 text-sm text-text-muted sm:grid-cols-2">
            <p><span class="font-medium text-text">Unidade:</span> {{ $this->panel->unit?->name ?? '—' }}</p>
            <p><span class="font-medium text-text">Setor:</span> {{ $this->panel->sectors->first()?->name ?? '—' }}</p>
        </div>
        <form wire:submit="add" class="mb-6 grid gap-3 rounded-xl border border-border bg-background p-4 md:grid-cols-[1fr_140px_auto]">
            <x-ui.select label="Mídia disponível" name="playlist_media" id="playlist_media" wire:model="mediaItemIdToAdd" required>
                <option value="">Selecione</option>
                @foreach ($this->availableMedia as $media)
                    <option value="{{ $media->id }}">{{ $media->name }} ({{ $media->type->label() }})</option>
                @endforeach
            </x-ui.select>
            <x-ui.input label="Duração (img)" name="playlist_duration" id="playlist_duration" type="number" min="3" max="300" wire:model="durationSeconds" />
            <div class="flex items-end">
                <x-ui.button type="submit" wire:loading.attr="disabled">Adicionar</x-ui.button>
            </div>
        </form>

        @if ($this->playlistEntries->isEmpty())
            <x-ui.empty-state title="Playlist vazia" description="Adicione mídias ativas da clínica. A TV usará o fallback institucional enquanto estiver vazia." />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Ordem</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Mídia</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Tipo</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Duração</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->playlistEntries as $entry)
                            <tr wire:key="playlist-{{ $entry->id }}" class="border-b border-border/70">
                                <td class="px-3 py-3 font-semibold text-text">{{ $entry->position }}</td>
                                <td class="px-3 py-3 text-text">{{ $entry->mediaItem?->name }}</td>
                                <td class="px-3 py-3 text-text-muted">{{ $entry->mediaItem?->type?->label() }}</td>
                                <td class="px-3 py-3">
                                    @if ($entry->mediaItem?->isImage())
                                        <input
                                            type="number"
                                            min="3"
                                            max="300"
                                            value="{{ $entry->duration_seconds ?? 10 }}"
                                            wire:change="updateDuration({{ $entry->id }}, Number($event.target.value))"
                                            class="w-20 rounded-lg border border-border px-2 py-1 text-sm"
                                            aria-label="Duração em segundos"
                                        >
                                    @elseif ($entry->mediaItem?->isYouTube())
                                        <span class="text-xs text-text-muted">Até o fim do YouTube</span>
                                    @else
                                        <span class="text-xs text-text-muted">Até o fim do vídeo</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($entry->active)
                                        <x-ui.badge tone="success">Ativo</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="warning">Inativo</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button variant="secondary" wire:click="moveUp({{ $entry->id }})">↑</x-ui.button>
                                        <x-ui.button variant="secondary" wire:click="moveDown({{ $entry->id }})">↓</x-ui.button>
                                        <x-ui.button variant="secondary" wire:click="toggleActive({{ $entry->id }})">
                                            {{ $entry->active ? 'Desativar' : 'Ativar' }}
                                        </x-ui.button>
                                        <x-ui.button variant="danger" wire:click="remove({{ $entry->id }})">Remover</x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</div>
