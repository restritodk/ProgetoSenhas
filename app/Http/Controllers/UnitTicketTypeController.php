<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\View\View;

class UnitTicketTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('manageAny', Unit::class);

        return view('unit-ticket-types.index');
    }
}
