<?php

namespace App\Http\Controllers;

use App\Services\DisplayPanelFeed;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TvPanelController extends Controller
{
    /**
     * Accepts short public_code or legacy public_token.
     * Livewire keeps using the long public_token so polling stays stable.
     */
    public function __invoke(string $publicToken, DisplayPanelFeed $feed): View|Response
    {
        $panel = $feed->findByPublicIdentifier($publicToken);

        if ($panel === null) {
            abort(404);
        }

        return view('tv.show', [
            'publicToken' => $panel->public_token,
            'panelName' => $panel->name,
        ]);
    }
}
