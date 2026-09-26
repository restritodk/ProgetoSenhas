<?php

namespace App\Actions;

use App\Models\TicketCall;
use App\Services\ClinicBranding;
use App\Services\ClinicSettings;
use App\Services\TvTts\TvTtsService;
use App\Support\TvPresentation;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

class WarmTicketCallAnnouncementAudio
{
    public function __construct(
        private TvTtsService $tts,
        private ClinicSettings $clinicSettings,
        private ClinicBranding $clinicBranding,
    ) {}

    /**
     * Synthesizes the call phrase after the response is sent, so the TV only
     * downloads an already stored file. Rolled-back calls are skipped.
     */
    public function schedule(TicketCall $call): void
    {
        $ticketCallId = (int) $call->id;

        defer(fn () => $this->handle($ticketCallId));
    }

    public function handle(int $ticketCallId): void
    {
        if (! config('tv_tts.enabled', true)) {
            return;
        }

        try {
            if (! $this->tts->isAvailable()) {
                return;
            }

            $call = TicketCall::query()
                ->with(['clinic', 'ticket.ticketType:id,name,prefix', 'desk:id,name,code'])
                ->whereKey($ticketCallId)
                ->first();

            if ($call === null) {
                return;
            }

            $this->tts->ensureCachedWav($this->announcementText($call));
        } catch (\Throwable $exception) {
            Log::warning('tv.tts.warm_failed', [
                'exception' => $exception::class,
                'ticket_call_id' => $ticketCallId,
            ]);
        }
    }

    /**
     * Same phrase the TV endpoint serves, honoring the clinic speech settings.
     */
    public function announcementText(TicketCall $call): string
    {
        $call->loadMissing('clinic');

        $presentation = $call->clinic !== null
            ? TvPresentation::forClinic($call->clinic, $this->clinicSettings, $this->clinicBranding)->toArray()
            : [];

        return $this->tts->announcementForCall(
            $call,
            (bool) ($presentation['speak_ticket_type'] ?? true),
            (bool) ($presentation['speak_desk'] ?? true),
        );
    }
}
