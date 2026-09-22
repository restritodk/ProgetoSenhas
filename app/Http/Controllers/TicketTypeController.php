<?php

namespace App\Http\Controllers;

use App\Models\TicketType;
use Illuminate\View\View;

class TicketTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', TicketType::class);

        return view('ticket-types.index');
    }
}
