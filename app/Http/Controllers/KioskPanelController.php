<?php

namespace App\Http\Controllers;

use App\Models\Kiosk;
use Illuminate\Http\Response;
use Illuminate\View\View;

class KioskPanelController extends Controller
{
    /**
     * Accepts short public_code or legacy public_token.
     * Livewire keeps using the long public_token so emission/print stays stable.
     */
    public function __invoke(string $publicToken): View|Response
    {
        $kiosk = Kiosk::query()
            ->where(function ($query) use ($publicToken): void {
                $query->where('public_code', $publicToken)
                    ->orWhere('public_token', $publicToken);
            })
            ->first();

        if ($kiosk === null) {
            abort(404);
        }

        return view('kiosk.show', [
            'publicToken' => $kiosk->public_token,
        ]);
    }
}
