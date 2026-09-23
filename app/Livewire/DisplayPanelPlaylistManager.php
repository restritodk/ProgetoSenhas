<?php

namespace App\Livewire;

use App\Actions\AttachMediaToDisplayPanel;
use App\Actions\DetachMediaFromDisplayPanel;
use App\Actions\ReorderDisplayPanelMedia;
use App\Actions\UpdateDisplayPanelMediaItem;
use App\Models\DisplayPanel;
use App\Models\DisplayPanelMedia;
use App\Models\MediaItem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DisplayPanelPlaylistManager extends Component
{
    public int $panelId;

    public ?int $mediaItemIdToAdd = null;

    public int $durationSeconds = 10;

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(int $panelId): void
    {
        $this->panelId = $panelId;
        $this->authorize('update', $this->panel);
    }

    public function add(AttachMediaToDisplayPanel $attachMediaToDisplayPanel): void
    {
        $this->validate([
            'mediaItemIdToAdd' => ['required', 'integer'],
            'durationSeconds' => ['nullable', 'integer', 'min:3', 'max:300'],
        ]);

        $panel = $this->panel;
        $media = MediaItem::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($this->mediaItemIdToAdd)
            ->firstOrFail();

        try {
            $attachMediaToDisplayPanel->handle(auth()->user(), $panel, $media, $this->durationSeconds);
            $this->statusMessage = 'Mídia adicionada à playlist.';
            $this->errorMessage = '';
            $this->mediaItemIdToAdd = null;
            $this->forgetComputed();
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível adicionar.';
            $this->statusMessage = '';
        }
    }

    public function remove(int $entryId, DetachMediaFromDisplayPanel $detachMediaFromDisplayPanel): void
    {
        $panel = $this->panel;
        $entry = $this->entryForPanel($entryId);
        $detachMediaFromDisplayPanel->handle(auth()->user(), $panel, $entry);
        $this->statusMessage = 'Mídia removida da playlist.';
        $this->forgetComputed();
    }

    public function moveUp(int $entryId, ReorderDisplayPanelMedia $reorderDisplayPanelMedia): void
    {
        $panel = $this->panel;
        $entry = $this->entryForPanel($entryId);
        $reorderDisplayPanelMedia->handle(auth()->user(), $panel, $entry, 'up');
        $this->forgetComputed();
    }

    public function moveDown(int $entryId, ReorderDisplayPanelMedia $reorderDisplayPanelMedia): void
    {
        $panel = $this->panel;
        $entry = $this->entryForPanel($entryId);
        $reorderDisplayPanelMedia->handle(auth()->user(), $panel, $entry, 'down');
        $this->forgetComputed();
    }

    public function toggleActive(int $entryId, UpdateDisplayPanelMediaItem $updateDisplayPanelMediaItem): void
    {
        $panel = $this->panel;
        $entry = $this->entryForPanel($entryId);
        $updateDisplayPanelMediaItem->handle(auth()->user(), $panel, $entry, [
            'active' => ! $entry->active,
        ]);
        $this->forgetComputed();
    }

    public function updateDuration(int $entryId, int $durationSeconds, UpdateDisplayPanelMediaItem $updateDisplayPanelMediaItem): void
    {
        $panel = $this->panel;
        $entry = $this->entryForPanel($entryId);

        try {
            $updateDisplayPanelMediaItem->handle(auth()->user(), $panel, $entry, [
                'duration_seconds' => $durationSeconds,
            ]);
            $this->statusMessage = 'Duração atualizada.';
            $this->errorMessage = '';
            $this->forgetComputed();
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Duração inválida.';
        }
    }

    #[Computed]
    public function panel(): DisplayPanel
    {
        return DisplayPanel::query()
            ->with('unit:id,name')
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($this->panelId)
            ->firstOrFail();
    }

    /**
     * @return Collection<int, DisplayPanelMedia>
     */
    #[Computed]
    public function playlistEntries(): Collection
    {
        return DisplayPanelMedia::query()
            ->with(['mediaItem'])
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->where('display_panel_id', $this->panelId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, MediaItem>
     */
    #[Computed]
    public function availableMedia(): Collection
    {
        $usedIds = $this->playlistEntries->pluck('media_item_id');

        return MediaItem::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->where('active', true)
            ->whereIn('type', ['image', 'video', 'youtube'])
            ->whereNotIn('id', $usedIds)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'mime_type']);
    }

    public function render(): View
    {
        return view('livewire.display-panel-playlist-manager');
    }

    private function entryForPanel(int $entryId): DisplayPanelMedia
    {
        return DisplayPanelMedia::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->where('display_panel_id', $this->panelId)
            ->whereKey($entryId)
            ->firstOrFail();
    }

    private function forgetComputed(): void
    {
        unset($this->playlistEntries, $this->availableMedia, $this->panel);
    }
}
