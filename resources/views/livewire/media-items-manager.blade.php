<div>
    @if ($statusMessage !== '')
        <x-ui.alert type="success" class="mb-4">{{ $statusMessage }}</x-ui.alert>
    @endif
    @if ($errorMessage !== '' && ! $showForm)
        <x-ui.alert type="danger" class="mb-4">{{ $errorMessage }}</x-ui.alert>
    @endif

    <x-ui.card title="Mídia da TV" description="Conteúdo institucional público para a área de mídia dos painéis. Não envie documentos ou informações de pacientes.">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-3 md:grid-cols-3">
                <x-ui.input label="Buscar" name="media_search" id="media_search" wire:model.live.debounce.400ms="search" placeholder="Nome" />
                <x-ui.select label="Tipo" name="media_type_filter" id="media_type_filter" wire:model.live="typeFilter">
                    <option value="">Todos</option>
                    <option value="image">Imagem</option>
                    <option value="video">Vídeo</option>
                    <option value="youtube">YouTube</option>
                </x-ui.select>
                <x-ui.select label="Status" name="media_status_filter" id="media_status_filter" wire:model.live="statusFilter">
                    <option value="">Todos</option>
                    <option value="active">Ativos</option>
                    <option value="inactive">Inativos</option>
                </x-ui.select>
            </div>
            <x-ui.button wire:click="startCreate" wire:loading.attr="disabled" id="media-open-create">
                <x-admin.icon name="plus" class="size-4" />
                Nova mídia
            </x-ui.button>
        </div>

        @if ($mediaItems->isEmpty() && ! $showForm)
            <x-ui.empty-state title="Nenhuma mídia cadastrada." description="Cadastre imagens, vídeos ou links do YouTube para as TVs.">
                <x-ui.button wire:click="startCreate">Nova mídia</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th scope="col" class="px-3 py-3 font-semibold">Preview</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Nome</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Tipo</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Exibição</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                            <th scope="col" class="px-3 py-3 font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mediaItems as $media)
                            <tr wire:key="media-{{ $media->id }}" class="border-b border-border/70 align-top">
                                <td class="px-3 py-3">
                                    @if ($media->isImage() && $media->publicUrl())
                                        <img src="{{ $media->publicUrl() }}" alt="" class="h-16 w-24 rounded-lg border border-border bg-background object-cover">
                                    @elseif ($media->isVideo() && $media->publicUrl())
                                        <div class="relative h-16 w-28 overflow-hidden rounded-lg border border-border bg-black">
                                            <video src="{{ $media->publicUrl() }}" muted class="h-full w-full object-cover"></video>
                                            <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-white/90" aria-hidden="true">▶</span>
                                        </div>
                                    @elseif ($media->isYouTube() && $media->youtubeThumbnailUrl())
                                        <div class="relative h-16 w-28 overflow-hidden rounded-lg border border-border bg-black">
                                            <img src="{{ $media->youtubeThumbnailUrl() }}" alt="" class="h-full w-full object-cover">
                                            <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-white/90" aria-hidden="true">▶</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 font-medium text-text">{{ $media->name }}</td>
                                <td class="px-3 py-3">
                                    @if ($media->isYouTube())
                                        <div class="space-y-1">
                                            <x-ui.badge tone="accent">YouTube</x-ui.badge>
                                            <p class="text-xs text-text-muted">{{ $media->play_with_audio ? 'Com áudio' : 'Sem áudio' }}</p>
                                        </div>
                                    @elseif ($media->isVideo())
                                        <div class="space-y-1">
                                            <x-ui.badge>Vídeo</x-ui.badge>
                                            <p class="text-xs text-text-muted">{{ $media->play_with_audio ? 'Com áudio' : 'Sem áudio' }}</p>
                                        </div>
                                    @else
                                        <x-ui.badge>Imagem</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-text-muted">
                                    @php
                                        $panelNames = $media->displayPanels->pluck('name')->filter()->values();
                                        $panelCount = $panelNames->count();
                                    @endphp
                                    @if ($panelCount === 0)
                                        <span title="Não exibida em nenhuma TV até ser adicionada a uma playlist.">Nenhum painel</span>
                                    @elseif ($panelCount === 1)
                                        <span title="{{ $panelNames->first() }}">{{ $panelNames->first() }}</span>
                                    @else
                                        <span title="{{ $panelNames->implode(', ') }}">{{ $panelCount }} painéis</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($media->active)
                                        <x-ui.badge tone="success">Ativo</x-ui.badge>
                                    @else
                                        <x-ui.badge tone="warning">Inativo</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <x-ui.button variant="secondary" wire:click="edit({{ $media->id }})">Editar</x-ui.button>
                                        @if ($media->active)
                                            <x-ui.button variant="secondary" wire:click="deactivate({{ $media->id }})">Desativar</x-ui.button>
                                        @else
                                            <x-ui.button variant="secondary" wire:click="activate({{ $media->id }})">Ativar</x-ui.button>
                                        @endif
                                        <x-ui.button variant="danger" wire:click="confirmDeletion({{ $media->id }})">Excluir</x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $mediaItems->links() }}</div>
        @endif
    </x-ui.card>

    <x-ui.modal
        :title="$editingMediaId ? 'Editar mídia' : 'Nova mídia'"
        :open="$showForm"
        maxWidth="2xl"
        closeMethod="attemptBackdropClose"
        id="media-form-modal-title"
    >
        @if ($errorMessage !== '')
            <x-ui.alert type="danger" class="mb-4">{{ $errorMessage }}</x-ui.alert>
        @endif

        <form wire:submit.prevent="save" class="space-y-5 text-text" id="media-form">
            <x-ui.input label="Nome" name="media_name" id="media_name" wire:model="name" required maxlength="255" placeholder="Ex.: Campanha Outubro Rosa">
                <x-input-error :messages="$errors->get('name')" />
            </x-ui.input>

            <fieldset>
                <legend class="mb-2 block text-sm font-medium text-text">Tipo / origem</legend>
                @if ($editingMediaId !== null)
                    <p class="mb-2 text-xs text-text-muted">Para trocar o tipo da mídia, cadastre uma nova mídia.</p>
                    <div class="inline-flex items-center gap-2 rounded-xl border border-border bg-background px-4 py-3 text-sm font-semibold text-text">
                        @if ($type === 'youtube')
                            YouTube · Link
                        @elseif ($type === 'video')
                            Vídeo · Computador
                        @else
                            Imagem · Computador
                        @endif
                    </div>
                @else
                    <div class="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Tipo de mídia">
                        @foreach ([
                            'image' => ['title' => 'Imagem', 'hint' => 'Computador'],
                            'video' => ['title' => 'Vídeo', 'hint' => 'Computador'],
                            'youtube' => ['title' => 'YouTube', 'hint' => 'Link'],
                        ] as $value => $meta)
                            <label class="relative cursor-pointer rounded-xl border px-4 py-4 transition {{ $type === $value ? 'border-accent bg-blue-50 ring-2 ring-accent/30' : 'border-border bg-background hover:border-accent/40' }}">
                                <input type="radio" class="sr-only" wire:model.live="type" value="{{ $value }}">
                                <span class="block text-sm font-semibold text-text">{{ $meta['title'] }}</span>
                                <span class="mt-1 block text-xs text-text-muted">{{ $meta['hint'] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('type')" />
                @endif
            </fieldset>

            @if ($type === 'image' || $type === 'video')
                <div>
                    <label class="mb-1 block text-sm font-medium text-text">Arquivo</label>

                    @if ($editingMedia && $upload === null)
                        <div class="mb-3 overflow-hidden rounded-xl border border-border bg-background">
                            @if ($editingMedia->isImage() && $editingMedia->publicUrl())
                                <img src="{{ $editingMedia->publicUrl() }}" alt="" class="max-h-48 w-full object-contain bg-primary-dark/5">
                            @elseif ($editingMedia->isVideo() && $editingMedia->publicUrl())
                                <video src="{{ $editingMedia->publicUrl() }}" controls muted class="max-h-48 w-full bg-black"></video>
                            @endif
                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border px-3 py-2 text-xs text-text-muted">
                                <span>Arquivo atual · {{ $editingMedia->file_size ? number_format($editingMedia->file_size / 1024, 0, ',', '.').' KB' : '—' }}</span>
                            </div>
                        </div>
                    @endif

                    @if ($upload)
                        <div class="mb-3 overflow-hidden rounded-xl border border-border bg-background">
                            @if ($upload->isPreviewable() && $type === 'image')
                                <img src="{{ $upload->temporaryUrl() }}" alt="Preview" class="max-h-48 w-full object-contain bg-primary-dark/5">
                            @elseif ($upload->isPreviewable() && $type === 'video')
                                <video src="{{ $upload->temporaryUrl() }}" controls muted class="max-h-48 w-full bg-black"></video>
                            @endif
                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border px-3 py-2 text-xs text-text-muted">
                                <span>{{ $upload->getClientOriginalName() }} · {{ number_format($upload->getSize() / 1024, 0, ',', '.') }} KB</span>
                                <button type="button" wire:click="clearUpload" class="font-semibold text-accent hover:underline">Trocar arquivo</button>
                            </div>
                        </div>
                    @else
                        <div class="rounded-xl border border-dashed border-border bg-background px-4 py-8 text-center">
                            <p class="text-sm text-text">
                                {{ $type === 'video' ? 'Arraste ou escolha um vídeo' : 'Arraste ou escolha uma imagem' }}
                            </p>
                            <label class="mt-4 inline-flex cursor-pointer items-center justify-center rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-white">
                                {{ $type === 'video' ? 'Escolher vídeo' : ($editingMediaId ? 'Trocar '.($type === 'image' ? 'imagem' : 'vídeo') : 'Escolher '.($type === 'image' ? 'imagem' : 'vídeo')) }}
                                <input
                                    type="file"
                                    class="sr-only"
                                    wire:model="upload"
                                    accept="{{ $type === 'video' ? 'video/mp4' : 'image/jpeg,image/png,image/webp' }}"
                                >
                            </label>
                            <p class="mt-3 text-xs text-text-muted">
                                @if ($type === 'video')
                                    MP4 · máximo 100 MB
                                @else
                                    JPEG, PNG ou WebP · máximo 10 MB
                                @endif
                            </p>
                            <p class="mt-1 text-xs text-text-muted">{{ $phpUploadLimitLabel }}</p>
                        </div>
                    @endif

                    <div wire:loading wire:target="upload" class="mt-2 text-sm text-accent">Enviando arquivo...</div>
                    <x-input-error :messages="$errors->get('upload')" />
                </div>
            @endif

            @if ($type === 'image')
                <x-ui.input label="Duração na TV (segundos)" name="media_duration" id="media_duration" type="number" min="3" max="300" wire:model="durationSeconds">
                    <p class="mt-1 text-xs text-text-muted">
                        Usada como duração inicial ao vincular esta imagem a painéis. A duração efetiva por TV pode ser ajustada em Gerenciar playlist.
                    </p>
                    <x-input-error :messages="$errors->get('durationSeconds')" />
                </x-ui.input>
            @elseif ($type === 'video')
                <p class="rounded-xl border border-border bg-background px-3 py-2 text-xs text-text-muted">
                    O próximo conteúdo será exibido quando o vídeo terminar.
                </p>
            @elseif ($type === 'youtube')
                <div>
                    <x-ui.input
                        label="Link do YouTube"
                        name="media_youtube"
                        id="media_youtube"
                        wire:model.live.debounce.400ms="youtubeUrl"
                        placeholder="https://www.youtube.com/watch?v=..."
                        maxlength="500"
                    >
                        <p class="mt-1 text-xs text-text-muted">Cole a URL do vídeo. Não cole códigos HTML ou iframe.</p>
                        <x-input-error :messages="$errors->get('youtubeUrl')" />
                    </x-ui.input>

                    @if ($youtubePreview)
                        <div class="mt-3 overflow-hidden rounded-xl border border-border bg-background">
                            <div class="relative aspect-video bg-black">
                                <img
                                    src="{{ \App\Support\YouTubeUrl::thumbnailUrl($youtubePreview['video_id']) }}"
                                    alt="Preview YouTube"
                                    class="h-full w-full object-cover"
                                >
                                <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-4xl text-white/90" aria-hidden="true">▶</span>
                            </div>
                            <div class="px-3 py-2 text-xs text-text-muted">YouTube · vídeo reconhecido</div>
                        </div>
                    @endif

                    <p class="mt-3 rounded-xl border border-border bg-background px-3 py-2 text-xs text-text-muted">
                        O próximo conteúdo será exibido quando o vídeo terminar.
                    </p>
                </div>
            @endif

            @if ($type === 'video' || $type === 'youtube')
                <fieldset>
                    <legend class="mb-2 block text-sm font-medium text-text">Áudio do vídeo</legend>
                    <div class="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Áudio do vídeo">
                        <button
                            type="button"
                            wire:click="$set('playWithAudio', false)"
                            class="rounded-xl border px-4 py-3 text-left transition {{ ! $playWithAudio ? 'border-accent bg-blue-50 ring-2 ring-accent/30' : 'border-border bg-background hover:border-accent/40' }}"
                            aria-pressed="{{ $playWithAudio ? 'false' : 'true' }}"
                        >
                            <span class="block text-sm font-semibold text-text">Sem áudio</span>
                            <span class="mt-1 block text-xs text-text-muted">
                                Recomendado para TVs de recepção e maior compatibilidade com reprodução automática.
                            </span>
                        </button>
                        <button
                            type="button"
                            wire:click="$set('playWithAudio', true)"
                            class="rounded-xl border px-4 py-3 text-left transition {{ $playWithAudio ? 'border-accent bg-blue-50 ring-2 ring-accent/30' : 'border-border bg-background hover:border-accent/40' }}"
                            aria-pressed="{{ $playWithAudio ? 'true' : 'false' }}"
                        >
                            <span class="block text-sm font-semibold text-text">Com áudio</span>
                            <span class="mt-1 block text-xs text-text-muted">
                                O navegador pode bloquear autoplay com som. Se isso ocorrer, a mídia segue muted e a playlist não para. Independente do botão “Ativar som” das chamadas.
                            </span>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('playWithAudio')" />
                </fieldset>
            @endif

            <fieldset>
                <legend class="mb-2 block text-sm font-medium text-text">Exibir nos painéis / TVs</legend>
                @if ($clinicPanels->isEmpty())
                    <p class="rounded-xl border border-border bg-background px-3 py-2 text-xs text-text-muted">
                        Nenhum painel cadastrado nesta clínica. Cadastre em Painéis / TVs.
                    </p>
                @else
                    <div class="space-y-2 rounded-xl border border-border bg-background p-3">
                        @foreach ($clinicPanels as $panel)
                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 text-sm text-text hover:bg-surface">
                                <input
                                    type="checkbox"
                                    value="{{ $panel->id }}"
                                    wire:model.live="selectedPanelIds"
                                    class="size-4 rounded border-border text-accent"
                                >
                                <span class="font-medium">{{ $panel->name }}</span>
                                @unless ($panel->active)
                                    <x-ui.badge tone="warning">Inativo</x-ui.badge>
                                @endunless
                            </label>
                        @endforeach
                    </div>
                @endif
                @if (count($selectedPanelIds) === 0)
                    <p class="mt-2 text-xs text-text-muted">
                        Esta mídia ficará disponível na biblioteca, mas não será exibida em nenhum painel até ser adicionada a uma playlist.
                    </p>
                @endif
                <x-input-error :messages="$errors->get('selectedPanelIds')" />
            </fieldset>

            <label class="flex min-h-11 items-center gap-3 text-sm text-text">
                <input type="checkbox" wire:model="active" class="size-4 rounded border-border text-accent">
                Ativo
            </label>
        </form>

        <x-slot:actions>
            <x-ui.button variant="secondary" type="button" wire:click="cancel" wire:loading.attr="disabled" wire:target="save,upload,confirmPanelDetachAndSave">
                Cancelar
            </x-ui.button>
            <x-ui.button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,upload,confirmPanelDetachAndSave">
                <span wire:loading.remove wire:target="save,confirmPanelDetachAndSave">{{ $editingMediaId ? 'Salvar alterações' : 'Cadastrar mídia' }}</span>
                <span wire:loading wire:target="save,confirmPanelDetachAndSave">Salvando...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal title="Remover dos painéis" :open="$confirmingPanelDetach" closeMethod="cancelPanelDetachConfirmation">
        <p>Esta mídia será removida da playlist dos painéis desmarcados. Ordem e duração dos demais vínculos serão preservadas.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelPanelDetachConfirmation">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="confirmPanelDetachAndSave" wire:loading.attr="disabled">Confirmar remoção</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal title="Excluir mídia" :open="$mediaPendingDeletionId !== null" closeMethod="cancelDeletion">
        <p>A exclusão remove o arquivo do armazenamento (quando houver). Se a mídia estiver em playlists, a operação será bloqueada.</p>
        <x-slot:actions>
            <x-ui.button variant="secondary" wire:click="cancelDeletion">Cancelar</x-ui.button>
            <x-ui.button variant="danger" wire:click="delete" wire:loading.attr="disabled">Excluir</x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
</div>
