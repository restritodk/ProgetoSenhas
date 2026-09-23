<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class UnitTicketTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('unit_ticket_types.manage');

        return view('unit-ticket-types.index');
    }
}
