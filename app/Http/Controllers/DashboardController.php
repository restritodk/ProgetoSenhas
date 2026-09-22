<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $clinic = $user->clinic;

        $unitsQuery = Unit::query()->where('clinic_id', $user->clinic_id);

        if (! $user->isAdministrator()) {
            $unitsQuery->whereIn('id', $user->units()->select('units.id'));
        }

        return view('dashboard', [
            'clinic' => $clinic,
            'unitCount' => (clone $unitsQuery)->count(),
            'activeUnitCount' => (clone $unitsQuery)->where('active', true)->count(),
            'userCount' => $user->can('viewAny', User::class)
                ? User::query()->where('clinic_id', $user->clinic_id)->count()
                : null,
        ]);
    }
}
