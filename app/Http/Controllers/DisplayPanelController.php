<?php

namespace App\Http\Controllers;

use App\Models\DisplayPanel;
use Illuminate\View\View;

class DisplayPanelController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', DisplayPanel::class);

        return view('display-panels.index');
    }
}
