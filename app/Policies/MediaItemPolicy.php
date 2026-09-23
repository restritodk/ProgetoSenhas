<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\MediaItem;
use App\Models\User;

class MediaItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('media.view');
    }

    public function view(User $user, MediaItem $mediaItem): bool
    {
        return $this->sameActiveClinic($user, $mediaItem->clinic_id) && $user->hasPermission('media.view');
    }

    public function create(User $user): bool
    {
        return $this->activeClinic($user) && $user->hasPermission('media.create');
    }

    public function update(User $user, MediaItem $mediaItem): bool
    {
        return $this->sameActiveClinic($user, $mediaItem->clinic_id)
            && ($user->hasPermission('media.update') || $user->hasPermission('media.manage_status'));
    }

    public function delete(User $user, MediaItem $mediaItem): bool
    {
        return $this->sameActiveClinic($user, $mediaItem->clinic_id) && $user->hasPermission('media.delete');
    }

    private function sameActiveClinic(User $user, ?int $clinicId): bool
    {
        return $this->activeClinic($user) && $user->clinic_id === $clinicId;
    }

    private function activeClinic(User $user): bool
    {
        return $user->active
            && $user->clinic_id !== null
            && Clinic::query()->whereKey($user->clinic_id)->where('active', true)->exists();
    }
}
