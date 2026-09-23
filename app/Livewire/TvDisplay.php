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

            if ($announce && $newCallId !== null && $newCallId !== $this->lastAnnouncedCallId) {
                $this->highlight = true;
                $this->dispatch(
                    'tv-new-call',
                    callId: $newCallId,
                    announcement: $newCall['announcement'] ?? '',
                    displayCode: $newCall['display_code'] ?? '',
                );
            } else {
                $this->highlight = false;
            }

            $this->currentCall = $newCall;
            $this->lastAnnouncedCallId = $newCallId ?? $this->lastAnnouncedCallId;
        } catch (\Throwable) {
            $this->connectionStatus = 'reconnecting';
        }
    }
}
