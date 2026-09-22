<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendantPanelController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->canAccessAttendantPanel(), 403);

        return view('attendant.index');
    }
}
