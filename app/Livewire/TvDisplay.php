<?php

namespace App\Livewire;

use App\Services\DisplayPanelFeed;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TvDisplay extends Component
{
    public string $publicToken = '';

    public bool $available = false;

    public string $panelName = '';

    public string $clinicName = '';

    public string $unitName = '';

    public string $connectionStatus = 'online';

    /** @var array<string, mixed>|null */
    public ?array $currentCall = null;

    /** @var list<array<string, mixed>> */
    public array $recentCalls = [];

    /** @var array<string, mixed> */
    public array $presentation = [];

    public ?int $lastAnnouncedCallId = null;

    public bool $highlight = false;

    public function mount(string $publicToken, DisplayPanelFeed $feed): void
    {
        $this->publicToken = $publicToken;
        $this->syncFeed($feed, announce: false);
    }

    public function refreshFeed(DisplayPanelFeed $feed): void
    {
        $this->syncFeed($feed, announce: true);
    }

    public function render(): View
    {
        return view('livewire.tv-display');
    }

    private function syncFeed(DisplayPanelFeed $feed, bool $announce): void
    {
        $panel = $feed->findByPublicToken($this->publicToken);

        if ($panel === null) {
            $this->connectionStatus = 'offline';
            $this->available = false;
            $this->currentCall = null;
            $this->recentCalls = [];

            return;
        }

        try {
            $payload = $feed->build($panel);
            $this->connectionStatus = 'online';
            $this->available = $payload['available'];
            $this->panelName = $payload['panel_name'];
            $this->clinicName = $payload['clinic_name'];
            $this->unitName = $payload['unit_name'];
            $this->recentCalls = $payload['recent_calls'];
            $this->presentation = $payload['presentation'] ?? [];

            $newCall = $payload['current_call'];
            $newCallId = is_array($newCall) ? (int) $newCall['id'] : null;
            $pending = $announce ? $this->pendingAnnouncements($payload) : [];

            // Cursor is TicketCall.id — role of caller is irrelevant to the TV feed.
            // Calls that arrived between polls are queued oldest-first so voices do not overlap.
            if ($pending !== []) {
                $this->highlight = true;
                $batch = [];

                foreach ($pending as $call) {
                    $callId = (int) $call['id'];
                    $announcement = (string) ($call['announcement'] ?? '');
                    $displayCode = (string) ($call['display_code'] ?? '');
                    $audioUrl = isset($call['audio_url']) && is_string($call['audio_url'])
                        ? $call['audio_url']
                        : null;

                    $this->dispatch(
                        'tv-new-call',
                        callId: $callId,
                        announcement: $announcement,
                        displayCode: $displayCode,
                        audioUrl: $audioUrl,
                    );

                    $batch[] = [
                        'panelToken' => $this->publicToken,
                        'callId' => $callId,
                        'announcement' => $announcement,
                        'displayCode' => $displayCode,
                        'audioUrl' => $audioUrl,
                    ];
                }

                // Deliver AFTER DOM morph so Alpine remounts cannot swallow the announce.
                // Dedup / playback state is per browser tab + panel token (no global consume).
                $this->js(
                    'window.__humanaTvCallAudio && window.__humanaTvCallAudio.enqueue('
                    .json_encode($batch, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
                    .')'
                );
            } else {
                $this->highlight = false;
            }

            $this->currentCall = $newCall;
            if (! $announce && $newCallId === null && $this->lastAnnouncedCallId === null) {
                // Empty panel: later calls (including two that arrive in one poll) are all new.
                $this->lastAnnouncedCallId = 0;
            } else {
                $this->lastAnnouncedCallId = $newCallId ?? $this->lastAnnouncedCallId;
            }
        } catch (\Throwable) {
            $this->connectionStatus = 'reconnecting';
        }
    }

    /**
     * Calls newer than the panel cursor, oldest first.
     * A null cursor (first successful view) announces only the current call.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function pendingAnnouncements(array $payload): array
    {
        $current = $payload['current_call'] ?? null;

        if ($this->lastAnnouncedCallId === null) {
            return is_array($current) && (int) ($current['id'] ?? 0) > 0 ? [$current] : [];
        }

        $cursor = $this->lastAnnouncedCallId;
        $byId = [];

        $candidates = [];
        if (is_array($current)) {
            $candidates[] = $current;
        }

        foreach ($payload['recent_calls'] ?? [] as $call) {
            if (is_array($call)) {
                $candidates[] = $call;
            }
        }

        foreach ($candidates as $call) {
            $id = (int) ($call['id'] ?? 0);
            if ($id > $cursor) {
                $byId[$id] = $call;
            }
        }

        ksort($byId);

        return array_values($byId);
    }
}
