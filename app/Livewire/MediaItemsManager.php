<?php

namespace App\Livewire;

use App\Actions\CreateMediaItem;
use App\Actions\DeleteMediaItem;
use App\Actions\SyncMediaItemDisplayPanels;
use App\Actions\UpdateMediaItem;
use App\MediaType;
use App\Models\DisplayPanel;
use App\Models\DisplayPanelMedia;
use App\Models\MediaItem;
use App\Support\YouTubeUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MediaItemsManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingMediaId = null;

    public string $name = '';

    public string $type = 'image';

    public bool $active = true;

    public int $durationSeconds = 10;

    public bool $playWithAudio = false;

    public $upload = null;

    public string $youtubeUrl = '';

    /** @var list<int|string> */
    public array $selectedPanelIds = [];

    /** @var list<int> */
    public array $originalPanelIds = [];

    public bool $confirmingPanelDetach = false;

    public ?int $mediaPendingDeletionId = null;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(): void
    {
        $this->authorize('viewAny', MediaItem::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedType(string $value): void
    {
        if ($this->editingMediaId !== null) {
            return;
        }

        $this->resetValidation();
        $this->upload = null;
        $this->youtubeUrl = '';
        $this->errorMessage = '';

        if ($value === MediaType::IMAGE->value) {
            $this->durationSeconds = MediaItem::DEFAULT_IMAGE_DURATION_SECONDS;
            $this->playWithAudio = false;
        } else {
            $this->playWithAudio = false;
        }
    }

    public function updatedUpload(): void
    {
        if ($this->type === MediaType::IMAGE->value) {
            $this->validateOnly('upload', [
                'upload' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.MediaItem::IMAGE_MAX_KILOBYTES],
            ], $this->uploadMessages());
        } elseif ($this->type === MediaType::VIDEO->value) {
            $this->validateOnly('upload', [
                'upload' => ['nullable', 'file', 'mimetypes:video/mp4', 'max:'.MediaItem::VIDEO_MAX_KILOBYTES],
            ], $this->uploadMessages());
        }
    }

    public function updatedYoutubeUrl(): void
    {
        $this->resetErrorBag('youtubeUrl');
    }

    public function clearUpload(): void
    {
        $this->upload = null;
        $this->resetValidation('upload');
    }

    public function startCreate(): void
    {
        $this->authorize('create', MediaItem::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $mediaId): void
    {
        $media = $this->mediaForCurrentClinic($mediaId);
        $this->authorize('update', $media);

        $panelIds = DisplayPanelMedia::query()
            ->where('clinic_id', $media->clinic_id)
            ->where('media_item_id', $media->id)
            ->pluck('display_panel_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $this->resetValidation();
        $this->editingMediaId = $media->id;
        $this->name = $media->name;
        $this->type = $media->type->value;
        $this->active = $media->active;
        $this->durationSeconds = $media->duration_seconds ?? MediaItem::DEFAULT_IMAGE_DURATION_SECONDS;
        $this->playWithAudio = $media->supportsAudioSetting() && (bool) $media->play_with_audio;
        $this->upload = null;
        $this->youtubeUrl = $media->isYouTube() ? (string) ($media->external_url ?? '') : '';
        $this->selectedPanelIds = $panelIds;
        $this->originalPanelIds = $panelIds;
        $this->confirmingPanelDetach = false;
        $this->showForm = true;
        $this->statusMessage = '';
        $this->errorMessage = '';
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function cancelPanelDetachConfirmation(): void
    {
        $this->confirmingPanelDetach = false;
    }

    public function confirmPanelDetachAndSave(
        CreateMediaItem $createMediaItem,
        UpdateMediaItem $updateMediaItem,
        SyncMediaItemDisplayPanels $syncMediaItemDisplayPanels,
    ): void {
        $this->confirmingPanelDetach = false;
        $this->save($createMediaItem, $updateMediaItem, $syncMediaItemDisplayPanels, true);
    }

    public function attemptBackdropClose(): void
    {
        if ($this->hasImportantPendingChanges()) {
            return;
        }

        $this->cancel();
    }

    public function save(
        CreateMediaItem $createMediaItem,
        UpdateMediaItem $updateMediaItem,
        SyncMediaItemDisplayPanels $syncMediaItemDisplayPanels,
        bool $forceDetach = false,
    ): void {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $this->errorMessage = '';

        if ($this->editingMediaId !== null && ! $forceDetach && $this->willDetachPanels()) {
            $this->confirmingPanelDetach = true;

            return;
        }

        if ($this->editingMediaId !== null) {
            $this->saveEdit($actor, $updateMediaItem, $syncMediaItemDisplayPanels);

            return;
        }

        $this->saveCreate($actor, $createMediaItem, $syncMediaItemDisplayPanels);
    }

    public function confirmDeletion(int $mediaId): void
    {
        $media = $this->mediaForCurrentClinic($mediaId);
        $this->authorize('delete', $media);
        $this->mediaPendingDeletionId = $media->id;
        $this->errorMessage = '';
    }

    public function cancelDeletion(): void
    {
        $this->mediaPendingDeletionId = null;
    }

    public function delete(DeleteMediaItem $deleteMediaItem): void
    {
        abort_if($this->mediaPendingDeletionId === null, 404);

        $actor = auth()->user();
        $media = $this->mediaForCurrentClinic($this->mediaPendingDeletionId);

        try {
            $deleteMediaItem->handle($actor, $media);
            $this->statusMessage = 'Mídia excluída.';
            $this->errorMessage = '';
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível excluir.';
            $this->statusMessage = '';
        }

        $this->mediaPendingDeletionId = null;
        $this->resetPage();
    }

    public function activate(int $mediaId, UpdateMediaItem $updateMediaItem): void
    {
        $actor = auth()->user();
        $media = $this->mediaForCurrentClinic($mediaId);
        $updateMediaItem->handle($actor, $media, [
            'name' => $media->name,
            'active' => true,
            'duration_seconds' => $media->duration_seconds,
            'play_with_audio' => $media->play_with_audio,
            'youtube_url' => $media->external_url,
        ]);
        $this->statusMessage = 'Mídia ativada.';
        $this->resetPage();
    }

    public function deactivate(int $mediaId, UpdateMediaItem $updateMediaItem): void
    {
        $actor = auth()->user();
        $media = $this->mediaForCurrentClinic($mediaId);
        $updateMediaItem->handle($actor, $media, [
            'name' => $media->name,
            'active' => false,
            'duration_seconds' => $media->duration_seconds,
            'play_with_audio' => $media->play_with_audio,
            'youtube_url' => $media->external_url,
        ]);
        $this->statusMessage = 'Mídia desativada.';
        $this->resetPage();
    }

    public function editingMedia(): ?MediaItem
    {
        if ($this->editingMediaId === null) {
            return null;
        }

        return MediaItem::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($this->editingMediaId)
            ->first();
    }

    public function youtubePreview(): ?array
    {
        if ($this->type !== MediaType::YOUTUBE->value) {
            return null;
        }

        return YouTubeUrl::tryParse($this->youtubeUrl);
    }

    /**
     * @return Collection<int, DisplayPanel>
     */
    public function clinicPanels(): Collection
    {
        return DisplayPanel::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->orderBy('name')
            ->get(['id', 'name', 'active']);
    }

    /**
     * @return LengthAwarePaginator<int, MediaItem>
     */
    public function mediaItems(): LengthAwarePaginator
    {
        $clinicId = auth()->user()?->clinic_id;

        return MediaItem::query()
            ->with(['displayPanels:id,name'])
            ->withCount('displayPanels')
            ->where('clinic_id', $clinicId)
            ->when($this->search !== '', function ($query): void {
                $term = '%'.Str::lower($this->search).'%';
                $query->whereRaw('LOWER(name) like ?', [$term]);
            })
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('active', false))
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function render(): View
    {
        return view('livewire.media-items-manager', [
            'mediaItems' => $this->mediaItems(),
            'editingMedia' => $this->editingMedia(),
            'youtubePreview' => $this->youtubePreview(),
            'clinicPanels' => $this->clinicPanels(),
            'phpUploadLimitLabel' => $this->phpUploadLimitLabel(),
        ]);
    }

    private function saveCreate(
        mixed $actor,
        CreateMediaItem $createMediaItem,
        SyncMediaItemDisplayPanels $syncMediaItemDisplayPanels,
    ): void {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([
                MediaType::IMAGE->value,
                MediaType::VIDEO->value,
                MediaType::YOUTUBE->value,
            ])],
            'active' => ['boolean'],
            'selectedPanelIds' => ['array'],
            'selectedPanelIds.*' => ['integer'],
        ];

        if ($this->type === MediaType::IMAGE->value) {
            $rules['upload'] = ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.MediaItem::IMAGE_MAX_KILOBYTES];
            $rules['durationSeconds'] = ['required', 'integer', 'min:3', 'max:300'];
        } elseif ($this->type === MediaType::VIDEO->value) {
            $rules['upload'] = ['required', 'file', 'mimetypes:video/mp4', 'max:'.MediaItem::VIDEO_MAX_KILOBYTES];
            $rules['playWithAudio'] = ['boolean'];
        } else {
            $rules['youtubeUrl'] = ['required', 'string', 'max:500'];
            $rules['playWithAudio'] = ['boolean'];
        }

        $this->validate($rules, array_merge($this->uploadMessages(), [
            'youtubeUrl.required' => 'Informe o link do YouTube.',
        ]));

        try {
            $item = $createMediaItem->handle($actor, [
                'name' => $this->name,
                'type' => $this->type,
                'active' => $this->active,
                'duration_seconds' => $this->durationSeconds,
                'play_with_audio' => $this->type !== MediaType::IMAGE->value && $this->playWithAudio,
                'youtube_url' => $this->youtubeUrl,
            ], $this->type === MediaType::YOUTUBE->value ? null : $this->upload);

            $syncMediaItemDisplayPanels->handle(
                $actor,
                $item,
                $this->selectedPanelIds,
                $this->type === MediaType::IMAGE->value ? $this->durationSeconds : null,
            );

            $panelCount = count($this->normalizedSelectedPanelIds());
            $this->resetForm();
            $this->statusMessage = $panelCount > 0
                ? "Mídia cadastrada e vinculada a {$panelCount} painel(is)."
                : 'Mídia cadastrada na biblioteca. Não será exibida até ser adicionada a uma playlist.';
            $this->resetPage();
        } catch (ValidationException $exception) {
            $this->applyActionErrors($exception);
        }
    }

    private function saveEdit(
        mixed $actor,
        UpdateMediaItem $updateMediaItem,
        SyncMediaItemDisplayPanels $syncMediaItemDisplayPanels,
    ): void {
        $media = $this->mediaForCurrentClinic((int) $this->editingMediaId);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'active' => ['boolean'],
            'selectedPanelIds' => ['array'],
            'selectedPanelIds.*' => ['integer'],
        ];

        if ($media->isImage()) {
            $rules['durationSeconds'] = ['required', 'integer', 'min:3', 'max:300'];
            $rules['upload'] = ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.MediaItem::IMAGE_MAX_KILOBYTES];
        } elseif ($media->isVideo()) {
            $rules['upload'] = ['nullable', 'file', 'mimetypes:video/mp4', 'max:'.MediaItem::VIDEO_MAX_KILOBYTES];
            $rules['playWithAudio'] = ['boolean'];
        } else {
            $rules['youtubeUrl'] = ['required', 'string', 'max:500'];
            $rules['playWithAudio'] = ['boolean'];
        }

        $this->validate($rules, array_merge($this->uploadMessages(), [
            'youtubeUrl.required' => 'Informe o link do YouTube.',
        ]));

        try {
            $updateMediaItem->handle($actor, $media, [
                'name' => $this->name,
                'active' => $this->active,
                'duration_seconds' => $this->durationSeconds,
                'play_with_audio' => $media->supportsAudioSetting() && $this->playWithAudio,
                'youtube_url' => $this->youtubeUrl,
            ], $this->upload);

            // Sync panels without rewriting duration/position of existing pivots.
            $syncMediaItemDisplayPanels->handle(
                $actor,
                $media->fresh(),
                $this->selectedPanelIds,
                $media->isImage() ? $this->durationSeconds : null,
            );

            $this->resetForm();
            $this->statusMessage = 'Mídia atualizada com sucesso.';
            $this->resetPage();
        } catch (ValidationException $exception) {
            $this->applyActionErrors($exception);
        }
    }

    private function applyActionErrors(ValidationException $exception): void
    {
        $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível salvar a mídia.';
        foreach ($exception->errors() as $key => $messages) {
            $field = match ($key) {
                'youtube_url' => 'youtubeUrl',
                'file' => 'upload',
                'mediaItemId' => 'selectedPanelIds',
                default => $key,
            };
            foreach ($messages as $message) {
                $this->addError($field, $message);
            }
        }
    }

    private function willDetachPanels(): bool
    {
        $selected = $this->normalizedSelectedPanelIds();
        $original = collect($this->originalPanelIds)->map(fn ($id): int => (int) $id)->all();

        return count(array_diff($original, $selected)) > 0;
    }

    /**
     * @return list<int>
     */
    private function normalizedSelectedPanelIds(): array
    {
        return collect($this->selectedPanelIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function mediaForCurrentClinic(int $mediaId): MediaItem
    {
        return MediaItem::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($mediaId)
            ->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingMediaId = null;
        $this->showForm = false;
        $this->name = '';
        $this->type = MediaType::IMAGE->value;
        $this->active = true;
        $this->durationSeconds = MediaItem::DEFAULT_IMAGE_DURATION_SECONDS;
        $this->playWithAudio = false;
        $this->upload = null;
        $this->youtubeUrl = '';
        $this->selectedPanelIds = [];
        $this->originalPanelIds = [];
        $this->confirmingPanelDetach = false;
        $this->errorMessage = '';
    }

    private function hasImportantPendingChanges(): bool
    {
        if ($this->upload !== null) {
            return true;
        }

        if ($this->editingMediaId === null) {
            return trim($this->name) !== ''
                || trim($this->youtubeUrl) !== ''
                || $this->selectedPanelIds !== [];
        }

        $media = $this->editingMedia();
        if ($media === null) {
            return false;
        }

        $selected = $this->normalizedSelectedPanelIds();
        $original = collect($this->originalPanelIds)->map(fn ($id): int => (int) $id)->sort()->values()->all();
        sort($selected);

        return $this->name !== $media->name
            || $this->active !== $media->active
            || ($media->supportsAudioSetting() && $this->playWithAudio !== (bool) $media->play_with_audio)
            || ($media->isImage() && $this->durationSeconds !== ($media->duration_seconds ?? MediaItem::DEFAULT_IMAGE_DURATION_SECONDS))
            || ($media->isYouTube() && trim($this->youtubeUrl) !== (string) ($media->external_url ?? ''))
            || $selected !== $original;
    }

    /**
     * @return array<string, string>
     */
    private function uploadMessages(): array
    {
        return [
            'upload.required' => 'Selecione um arquivo.',
            'upload.mimetypes' => 'Arquivo inválido para o tipo selecionado.',
            'upload.max' => 'O arquivo excede o tamanho máximo permitido.',
        ];
    }

    private function phpUploadLimitLabel(): string
    {
        $upload = ini_get('upload_max_filesize') ?: '?';
        $post = ini_get('post_max_size') ?: '?';

        return "Limite PHP deste ambiente: upload_max_filesize={$upload}, post_max_size={$post}.";
    }
}
