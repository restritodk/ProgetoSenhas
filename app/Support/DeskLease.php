<?php

namespace App\Support;

use App\Models\DeskAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class DeskLease
{
    public static function ttlSeconds(): int
    {
        return max(30, (int) config('desk_lease.ttl_seconds', 90));
    }

    public static function expiresBefore(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->subSeconds(self::ttlSeconds());
    }

    public static function isActive(?DeskAssignment $assignment): bool
    {
        if ($assignment === null || $assignment->last_seen_at === null) {
            return false;
        }

        return $assignment->last_seen_at->greaterThan(self::expiresBefore());
    }

    /**
     * @param  Builder<DeskAssignment>  $query
     * @return Builder<DeskAssignment>
     */
    public static function scopeActive(Builder $query): Builder
    {
        return $query->where('last_seen_at', '>', self::expiresBefore());
    }

    /**
     * Delete expired claims (optionally scoped). Safe for occupancy UI / reclaim.
     * Does not touch tickets or history.
     */
    public static function purgeExpired(?int $deskId = null): int
    {
        $query = DeskAssignment::query()
            ->where('last_seen_at', '<=', self::expiresBefore());

        if ($deskId !== null) {
            $query->where('desk_id', $deskId);
        }

        return $query->delete();
    }
}
