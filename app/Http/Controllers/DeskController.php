<?php

namespace App\Http\Controllers;

use App\Models\Desk;
use Illuminate\View\View;

class DeskController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Desk::class);

        return view('desks.index');
    }
}
