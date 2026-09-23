<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendantProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user() !== null && $request->user()->active, 403);

        return view('attendant.profile');
    }
}
