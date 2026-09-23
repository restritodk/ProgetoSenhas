<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

class UserPresence
{
    /** How often the attendant layout should report activity. */
    public const HEARTBEAT_SECONDS = 30;

    /** Consider online if last_seen_at is within this window. */
    public const ONLINE_WINDOW_SECONDS = 90;

    public function touch(User $user): void
    {
        if (! $user->active || $user->clinic_id === null) {
            return;
        }

        $user->forceFill([
            'last_seen_at' => now(config('app.timezone')),
        ])->saveQuietly();
    }

    public function markOffline(User $user): void
    {
        $user->forceFill([
            'last_seen_at' => null,
        ])->saveQuietly();
    }

    public function isOnline(?User $user, ?CarbonImmutable $at = null): bool
    {
        if ($user === null || ! $user->active || $user->last_seen_at === null) {
            return false;
        }

        $at ??= CarbonImmutable::now(config('app.timezone'));
        $seenAt = CarbonImmutable::parse($user->last_seen_at)->timezone(config('app.timezone'));

        return $seenAt->greaterThanOrEqualTo($at->subSeconds(self::ONLINE_WINDOW_SECONDS));
    }

    public function statusLabel(?User $user, ?CarbonImmutable $at = null): string
    {
        return $this->isOnline($user, $at) ? 'Online' : 'Offline';
    }
}
