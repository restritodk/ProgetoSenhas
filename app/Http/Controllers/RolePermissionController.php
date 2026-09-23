<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function index(): View
    {
        $this->authorize('roles.view');

        return view('roles.index');
    }
}
