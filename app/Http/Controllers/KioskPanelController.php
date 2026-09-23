<?php

namespace App\Http\Controllers;

use App\Models\Kiosk;
use Illuminate\Http\Response;
use Illuminate\View\View;

class KioskPanelController extends Controller
{
    public function __invoke(string $publicToken): View|Response
    {
        $exists = Kiosk::query()
            ->where('public_token', $publicToken)
            ->exists();

        if (! $exists) {
            abort(404);
        }

        return view('kiosk.show', [
            'publicToken' => $publicToken,
        ]);
    }
}
