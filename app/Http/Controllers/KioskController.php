<?php

namespace App\Http\Controllers;

use App\Models\Kiosk;
use Illuminate\View\View;

class KioskController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Kiosk::class);

        return view('kiosks.index');
    }
}
