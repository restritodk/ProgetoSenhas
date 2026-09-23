<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use Illuminate\View\View;

class SectorController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Sector::class);

        return view('sectors.index');
    }
}
