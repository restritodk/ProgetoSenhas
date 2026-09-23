<?php

namespace App\Actions;

use App\Models\DisplayPanelMedia;
use App\Models\MediaItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteMediaItem
{
    public function handle(User $actor, MediaItem $mediaItem): void
    {
        abort_if($mediaItem->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('delete', $mediaItem);

        $usageCount = DisplayPanelMedia::query()
            ->where('clinic_id', $mediaItem->clinic_id)
            ->where('media_item_id', $mediaItem->id)
            ->count();

        if ($usageCount > 0) {
            throw ValidationException::withMessages([
                'media' => "Esta mídia está sendo utilizada em {$usageCount} painel(is). Remova-a das playlists antes de excluir.",
            ]);
        }

        $path = $mediaItem->file_path;
        $disk = $mediaItem->disk ?: MediaItem::DISK;

        DB::transaction(function () use ($mediaItem): void {
            $mediaItem->delete();
        });

        if (is_string($path) && $path !== '' && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
