<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Desk;
use App\Models\DeskAssignment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;

class OperationalContext
{
    public const UNIT_SESSION_KEY = 'active_unit_id';

    public const DESK_SESSION_KEY = 'active_desk_id';

    /** @deprecated Use UNIT_SESSION_KEY */
    public const SESSION_KEY = self::UNIT_SESSION_KEY;

    public function activeUnit(User $user, Session|SessionManager|Store $session): ?Unit
    {
        $unitId = $session->get(self::UNIT_SESSION_KEY);

        if (! $this->userMayOperate($user) || ! $unitId) {
            $this->clear($session);

            return null;
        }

        $unit = Unit::query()->find($unitId);

        if (! $unit?->active || $unit->clinic_id !== $user->clinic_id || ! $user->canOperateUnit($unit)) {
            $this->clear($session);

            return null;
        }

        return $unit;
    }

    public function setActiveUnit(User $user, Unit $unit, Session|SessionManager|Store $session): bool
    {
        if (! $this->userMayOperate($user) || ! $unit->active || ! $user->canOperateUnit($unit)) {
            $this->clear($session);

            return false;
        }

        $previousUnitId = $session->get(self::UNIT_SESSION_KEY);
        $session->put(self::UNIT_SESSION_KEY, $unit->getKey());

        if ((int) $previousUnitId !== (int) $unit->getKey()) {
            $session->forget(self::DESK_SESSION_KEY);
        }

        return true;
    }

    public function activeDesk(User $user, Session|SessionManager|Store $session): ?Desk
    {
        $unit = $this->activeUnit($user, $session);
        $deskId = $session->get(self::DESK_SESSION_KEY);

        if ($unit === null || ! $deskId) {
            $session->forget(self::DESK_SESSION_KEY);

            return null;
        }

        $desk = Desk::query()->find($deskId);

        if (
            ! $desk?->active
            || $desk->clinic_id !== $user->clinic_id
            || $desk->unit_id !== $unit->id
        ) {
            $session->forget(self::DESK_SESSION_KEY);

            return null;
        }

        $assignment = DeskAssignment::query()
            ->where('desk_id', $desk->id)
            ->where('user_id', $user->id)
            ->first();

        if ($assignment === null) {
            $session->forget(self::DESK_SESSION_KEY);

            return null;
        }

        $assignment->forceFill(['last_seen_at' => now()])->save();

        return $desk;
    }

    public function setActiveDesk(User $user, Desk $desk, Session|SessionManager|Store $session): bool
    {
        $unit = $this->activeUnit($user, $session);

        if (
            $unit === null
            || ! $desk->active
            || $desk->clinic_id !== $user->clinic_id
            || $desk->unit_id !== $unit->id
        ) {
            $session->forget(self::DESK_SESSION_KEY);

            return false;
        }

        $session->put(self::DESK_SESSION_KEY, $desk->getKey());

        return true;
    }

    public function clearDesk(Session|SessionManager|Store $session): void
    {
        $session->forget(self::DESK_SESSION_KEY);
    }

    public function clear(Session|SessionManager|Store $session): void
    {
        $session->forget(self::UNIT_SESSION_KEY);
        $session->forget(self::DESK_SESSION_KEY);
    }

    private function userMayOperate(User $user): bool
    {
        return $user->active
            && $user->clinic_id !== null
            && Clinic::query()->whereKey($user->clinic_id)->where('active', true)->exists();
    }
}
