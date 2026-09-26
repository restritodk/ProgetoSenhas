<?php

namespace App\Http\Controllers;

use App\Actions\WarmTicketCallAnnouncementAudio;
use App\Models\TicketCall;
use App\Services\DisplayPanelFeed;
use App\Services\TvTts\TvTtsService;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TvTtsController extends Controller
{
    public function __invoke(
        string $publicToken,
        int $ticketCall,
        DisplayPanelFeed $feed,
        TvTtsService $tts,
        WarmTicketCallAnnouncementAudio $announcementAudio,
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

        $call->setRelation('clinic', $panel->clinic);

        $path = $tts->ensureCachedWav($announcementAudio->announcementText($call));
        if ($path === null || ! $tts->isServableCachePath($path)) {
            return response('TTS synthesis failed', 503);
        }

        return response()->file($path, [
            'Content-Type' => $tts->contentType(),
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
