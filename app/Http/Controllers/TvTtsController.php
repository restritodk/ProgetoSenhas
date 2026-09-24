<?php

namespace App\Http\Controllers;

use App\Models\TicketCall;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Services\DisplayPanelFeed;
use App\Services\TvTts\TvTtsService;
use App\Support\TvPresentation;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TvTtsController extends Controller
{
    public function __invoke(
        string $publicToken,
        int $ticketCall,
        DisplayPanelFeed $feed,
        TvTtsService $tts,
        ClinicSettings $clinicSettings,
        ClinicBranding $clinicBranding,
    ): BinaryFileResponse|Response {
        if (! $tts->isAvailable()) {
            return response('TTS unavailable', 503);
        }

        $panel = $feed->findByPublicIdentifier($publicToken);
        if ($panel === null || ! $panel->isOperationallyAvailable()) {
            abort(404);
        }

        $call = TicketCall::query()
            ->with(['ticket.ticketType:id,name,prefix', 'desk:id,name,code'])
            ->whereKey($ticketCall)
            ->first();

        if ($call === null || ! $tts->callBelongsToPanel($panel, $call)) {
            abort(404);
        }

        $presentation = $panel->clinic !== null
            ? TvPresentation::forClinic($panel->clinic, $clinicSettings, $clinicBranding)->toArray()
            : [];

        $speakType = (bool) ($presentation['speak_ticket_type'] ?? true);
        $speakDesk = (bool) ($presentation['speak_desk'] ?? true);
        $text = $tts->announcementForCall($call, $speakType, $speakDesk);

        $path = $tts->ensureCachedWav($text);
        if ($path === null || ! is_file($path)) {
            return response('TTS synthesis failed', 503);
        }

        return response()->file($path, [
            'Content-Type' => 'audio/wav',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
