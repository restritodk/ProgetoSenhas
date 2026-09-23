<?php

namespace App\Http\Controllers;

use App\Models\DisplayPanel;
use Illuminate\View\View;

class DisplayPanelPlaylistController extends Controller
{
    public function edit(DisplayPanel $panel): View
    {
        abort_if($panel->clinic_id !== auth()->user()?->clinic_id, 404);
        $this->authorize('update', $panel);

        return view('display-panels.playlist', [
            'panel' => $panel,
        ]);
    }
}
