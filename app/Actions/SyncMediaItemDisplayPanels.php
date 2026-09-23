<?php

namespace App\Actions;

use App\Models\DisplayPanel;
use App\Models\DisplayPanelMedia;
use App\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SyncMediaItemDisplayPanels
{
    public function __construct(
        private AttachMediaToDisplayPanel $attachMediaToDisplayPanel,
        private DetachMediaFromDisplayPanel $detachMediaFromDisplayPanel,
    ) {}

    /**
     * Sync playlist membership for a media item.
     * Existing pivot rows keep position/duration/active; only attach missing and detach removed.
     *
     * @param  list<int|string>  $panelIds
     */
    public function handle(User $actor, MediaItem $mediaItem, array $panelIds, ?int $initialDurationSeconds = null): void
    {
        abort_if($mediaItem->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('update', $mediaItem);

        $desiredIds = collect($panelIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $panels = DisplayPanel::query()
            ->where('clinic_id', $actor->clinic_id)
            ->whereIn('id', $desiredIds)
            ->get()
            ->keyBy('id');

        if ($desiredIds->count() !== $panels->count()) {
            throw ValidationException::withMessages([
                'selectedPanelIds' => 'Um ou mais painéis selecionados são inválidos.',
            ]);
        }

        foreach ($panels as $panel) {
            if ($panel->clinic_id !== $mediaItem->clinic_id) {
                throw ValidationException::withMessages([
                    'selectedPanelIds' => 'Não é permitido vincular mídia a painéis de outra clínica.',
                ]);
            }
        }

        $currentEntries = DisplayPanelMedia::query()
            ->where('clinic_id', $actor->clinic_id)
            ->where('media_item_id', $mediaItem->id)
            ->get()
            ->keyBy('display_panel_id');

        $currentIds = $currentEntries->keys()->map(fn ($id): int => (int) $id)->values();
        $toAttach = $desiredIds->diff($currentIds)->values();
        $toDetach = $currentIds->diff($desiredIds)->values();

        DB::transaction(function () use ($actor, $mediaItem, $panels, $currentEntries, $toAttach, $toDetach, $initialDurationSeconds): void {
            foreach ($toDetach as $panelId) {
                /** @var DisplayPanelMedia $entry */
                $entry = $currentEntries->get($panelId);
                $panel = DisplayPanel::query()
                    ->where('clinic_id', $actor->clinic_id)
                    ->whereKey($panelId)
                    ->firstOrFail();

                $this->detachMediaFromDisplayPanel->handle($actor, $panel, $entry);
            }

            foreach ($toAttach as $panelId) {
                /** @var DisplayPanel $panel */
                $panel = $panels->get($panelId);
                $this->attachMediaToDisplayPanel->handle(
                    $actor,
                    $panel,
                    $mediaItem,
                    $mediaItem->isImage() ? $initialDurationSeconds : null,
                );
            }
        });
    }
}
