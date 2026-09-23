<?php

namespace App\Http\Controllers;

use App\Services\OperationalChatEligibility;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendantMessagesController extends Controller
{
    public function __invoke(Request $request, OperationalChatEligibility $eligibility): View
    {
        abort_unless($eligibility->canUseOperationalChat($request->user()), 403);

        return view('attendant.messages');
    }
}
