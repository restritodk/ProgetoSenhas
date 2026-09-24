<?php

namespace App\Livewire;

use App\Services\DisplayPanelFeed;
use App\Services\DisplayPanelPlaylist;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TvMediaPlayer extends Component
{
    public string $publicToken = '';

    public string $clinicName = '';

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public string $playlistSignature = '';

    public function mount(string $publicToken, DisplayPanelFeed $feed, DisplayPanelPlaylist $playlist, string $clinicName = ''): void
    {
        $this->publicToken = $publicToken;
        $this->clinicName = $clinicName;
        $this->syncPlaylist($feed, $playlist);
    }

    public function refreshPlaylist(DisplayPanelFeed $feed, DisplayPanelPlaylist $playlist): void
    {
        $signatureBefore = $this->playlistSignature;
        $this->syncPlaylist($feed, $playlist);

        // TicketCall polls run on TvDisplay; this poll only re-renders when the playlist really changes.
        if ($this->playlistSignature === $signatureBefore) {
            $this->skipRender();
        }
    }

    public function render(): View
    {
        return view('livewire.tv-media-player');
    }

    private function syncPlaylist(DisplayPanelFeed $feed, DisplayPanelPlaylist $playlist): void
    {
        $panel = $feed->findByPublicToken($this->publicToken);

        if ($panel === null || ! $panel->isOperationallyAvailable()) {
            $this->items = [];
            $this->playlistSignature = 'empty';

            return;
        }

        $items = $playlist->forPanel($panel);
        $signature = md5(json_encode($items) ?: '[]');

        if ($signature !== $this->playlistSignature) {
            $this->items = $items;
            $this->playlistSignature = $signature;
            $this->dispatch('tv-playlist-updated', items: $items);
        }
    }
}
