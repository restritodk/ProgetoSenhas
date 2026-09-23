<?php

namespace App\Http\Controllers;

use App\Models\ClinicSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ClinicSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ClinicSetting::class);

        return view('settings.index');
    }
}
