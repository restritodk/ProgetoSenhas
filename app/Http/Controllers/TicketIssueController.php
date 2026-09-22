<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\View\View;

class TicketIssueController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', Ticket::class);

        return view('tickets.issue');
    }
}
