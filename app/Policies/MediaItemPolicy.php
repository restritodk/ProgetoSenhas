<?php

namespace App\Policies;

use App\Models\Clinic;
use App\Models\MediaItem;
use App\Models\User;

class MediaItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, MediaItem $mediaItem): bool
    {
        return $this->sameActiveClinic($user, $mediaItem->clinic_id) && $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, MediaItem $mediaItem): bool
    {
        return $this->sameActiveClinic($user, $mediaItem->clinic_id) && $user->isAdministrator();
    }

    public function delete(User $user, MediaItem $mediaItem): bool
    {
        return $this->update($user, $mediaItem);
    }

    private function canManage(User $user): bool
    {
        return $user->active && $user->isAdministrator() && $this->hasActiveClinic($user);
    }

    private function sameActiveClinic(User $user, ?int $clinicId): bool
    {
        return $user->active
            && $user->clinic_id !== null
            && $user->clinic_id === $clinicId
            && $this->hasActiveClinic($user);
    }

    private function hasActiveClinic(User $user): bool
    {
        return $user->clinic_id !== null
            && Clinic::query()->whereKey($user->clinic_id)->where('active', true)->exists();
    }
}
