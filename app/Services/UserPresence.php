<?php

namespace App\Services;

use App\Models\DeskAssignment;
use App\Models\User;
use App\Support\DeskLease;
use Carbon\CarbonImmutable;

/**
 * Chat Online/Offline presence.
 *
 * ATTENDANT: source of truth is an active desk lease (desk_assignments.last_seen_at).
 * SUPERVISOR: heartbeat via users.last_seen_at (no desk required for chat).
 * Desk lease TTL / purge remain the safety net when the browser disappears.
 */
class UserPresence
{
    /** @deprecated Use heartbeatSeconds() / config('user_presence.heartbeat_seconds') */
    public const HEARTBEAT_SECONDS = 30;

    /** @deprecated Use onlineWindowSeconds() / config('user_presence.online_window_seconds') */
    public const ONLINE_WINDOW_SECONDS = 90;

    public function heartbeatSeconds(): int
    {
        return max(5, (int) config('user_presence.heartbeat_seconds', self::HEARTBEAT_SECONDS));
    }

    public function onlineWindowSeconds(): int
    {
        return max(
            $this->heartbeatSeconds() + 1,
            (int) config('user_presence.online_window_seconds', self::ONLINE_WINDOW_SECONDS),
        );
    }

    public function unreadPollSeconds(): int
    {
        return max(3, (int) config('user_presence.unread_poll_seconds', 10));
    }

    /**
     * Keep the authenticated user's operational session alive while they navigate
     * the attendant shell (any page — not only /atendimento).
     */
    public function touch(User $user): void
    {
        if (! $user->active || $user->clinic_id === null) {
            return;
        }

        if ($user->isAttendant()) {
            $this->refreshActiveDeskLease($user);

            return;
        }

        if ($user->isSupervisor()) {
            $this->touchHeartbeat($user);
        }
    }

    public function markOffline(User $user): void
    {
        $user->forceFill([
            'last_seen_at' => null,
        ])->saveQuietly();
    }

    public function isOnline(?User $user, ?CarbonImmutable $at = null): bool
    {
        if ($user === null || ! $user->active) {
            return false;
        }

        if ($user->isAttendant()) {
            return $this->hasActiveDeskLease($user);
        }

        if ($user->isSupervisor()) {
            return $this->hasRecentHeartbeat($user, $at);
        }

        return false;
    }

    public function statusLabel(?User $user, ?CarbonImmutable $at = null): string
    {
        return $this->isOnline($user, $at) ? 'Online' : 'Offline';
    }

    public function hasActiveDeskLease(User $user): bool
    {
        $assignment = $user->relationLoaded('deskAssignment')
            ? $user->deskAssignment
            : DeskAssignment::query()
                ->where('user_id', $user->id)
                ->where('clinic_id', $user->clinic_id)
                ->first();

        return DeskLease::isActive($assignment);
    }

    /**
     * Refresh lease last_seen_at only when the claim is still within TTL.
     * Does not create a desk claim and does not depend on Messages page.
     */
    public function refreshActiveDeskLease(User $user): void
    {
        $assignment = DeskAssignment::query()
            ->where('user_id', $user->id)
            ->where('clinic_id', $user->clinic_id)
            ->first();

        if ($assignment === null || ! DeskLease::isActive($assignment)) {
            return;
        }

        $heartbeat = min($this->heartbeatSeconds(), max(5, (int) config('desk_lease.heartbeat_hint_seconds', 25)));
        $seenAt = CarbonImmutable::parse($assignment->last_seen_at)->timezone(config('app.timezone'));

        if ($seenAt->greaterThan(CarbonImmutable::now(config('app.timezone'))->subSeconds($heartbeat))) {
            return;
        }

        $assignment->forceFill([
            'last_seen_at' => now(config('app.timezone')),
        ])->save();
    }

    private function touchHeartbeat(User $user): void
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $heartbeat = $this->heartbeatSeconds();

        if ($user->last_seen_at !== null) {
            $seenAt = CarbonImmutable::parse($user->last_seen_at)->timezone(config('app.timezone'));

            if ($seenAt->greaterThan($now->subSeconds($heartbeat))) {
                return;
            }
        }

        $user->forceFill([
            'last_seen_at' => now(config('app.timezone')),
        ])->saveQuietly();
    }

    private function hasRecentHeartbeat(User $user, ?CarbonImmutable $at = null): bool
    {
        if ($user->last_seen_at === null) {
            return false;
        }

        $at ??= CarbonImmutable::now(config('app.timezone'));
        $seenAt = CarbonImmutable::parse($user->last_seen_at)->timezone(config('app.timezone'));

        return $seenAt->greaterThanOrEqualTo($at->subSeconds($this->onlineWindowSeconds()));
    }
}
