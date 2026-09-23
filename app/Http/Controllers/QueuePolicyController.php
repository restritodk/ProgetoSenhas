<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class QueuePolicyController extends Controller
{
    public function index(): View
    {
        $this->authorize('queue_policy.view');

        return view('queue-policies.index');
    }
}
