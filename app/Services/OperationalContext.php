<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;

class OperationalContext
{
    public const SESSION_KEY = 'active_unit_id';

    public function activeUnit(User $user, Session|SessionManager|Store $session): ?Unit
    {
        $unitId = $session->get(self::SESSION_KEY);

        $clinicIsActive = $user->clinic_id !== null
            && Clinic::query()->whereKey($user->clinic_id)->where('active', true)->exists();

        if (! $user->active || ! $clinicIsActive || ! $unitId) {
            $session->forget(self::SESSION_KEY);

            return null;
        }

        $unit = Unit::query()->find($unitId);

        if (! $unit?->active || $unit->clinic_id !== $user->clinic_id || ! $user->hasAccessToUnit($unit)) {
            $session->forget(self::SESSION_KEY);

            return null;
        }

        return $unit;
    }

    public function setActiveUnit(User $user, Unit $unit, Session|SessionManager|Store $session): bool
    {
        $clinicIsActive = $user->clinic_id !== null
            && Clinic::query()->whereKey($user->clinic_id)->where('active', true)->exists();

        if (! $user->active || ! $clinicIsActive || ! $unit->active || ! $user->hasAccessToUnit($unit)) {
            $session->forget(self::SESSION_KEY);

            return false;
        }

        $session->put(self::SESSION_KEY, $unit->getKey());

        return true;
    }
}
