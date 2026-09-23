<?php

namespace App\Livewire;

use App\Actions\OpenDirectConversation;
use App\Actions\SendClinicMessage;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Services\ClinicMessageInbox;
use App\Services\OperationalChatEligibility;
use App\Services\UserPresence;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AttendantMessages extends Component
{
    public ?int $activeConversationId = null;

    public string $body = '';

    public string $peerSearch = '';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public function mount(OperationalChatEligibility $eligibility): void
    {
        abort_unless($eligibility->canUseOperationalChat(auth()->user()), 403);
    }

    public function refreshInbox(): void
    {
        // Do not touch $body — polling must never clear the composer.
        unset(
            $this->directoryPeers,
            $this->conversations,
            $this->messages,
            $this->activeConversation,
            $this->peer,
            $this->peerOnline,
            $this->canSendToPeer,
        );
    }

    public function openConversation(int $conversationId): void
    {
        $conversation = $this->conversationForActor($conversationId);
        $peer = $this->peerForConversation($conversation);

        abort_unless(
            $peer !== null && app(OperationalChatEligibility::class)->canViewConversationWith(auth()->user(), $peer),
            403,
        );

        $this->activeConversationId = $conversation->id;
        $this->body = '';
        $this->errorMessage = '';
        $this->markRead($conversation);
        $this->forgetComputed();
    }

    public function startWithPeer(int $peerId, OpenDirectConversation $openDirectConversation): void
    {
        $actor = auth()->user();
        $eligibility = app(OperationalChatEligibility::class);

        abort_unless($eligibility->canUseOperationalChat($actor), 403);

        $peer = User::query()
            ->where('clinic_id', $actor?->clinic_id)
            ->whereIn('role', $eligibility->eligibleRoleValues())
            ->where('active', true)
            ->whereKey($peerId)
            ->firstOrFail();

        try {
            $conversation = $openDirectConversation->handle($actor, $peer);
            $this->activeConversationId = $conversation->id;
            $this->peerSearch = '';
            $this->statusMessage = '';
            $this->errorMessage = '';
            $this->body = '';
            $this->markRead($conversation);
            $this->forgetComputed();
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível abrir a conversa.';
            $this->statusMessage = '';
        }
    }

    public function sendMessage(SendClinicMessage $sendClinicMessage): void
    {
        $conversation = $this->activeConversation;
        abort_if($conversation === null, 404);

        try {
            $sendClinicMessage->handle(auth()->user(), $conversation, $this->body);
            $this->body = '';
            $this->errorMessage = '';
            $this->statusMessage = '';
            unset($this->directoryPeers, $this->conversations, $this->messages);
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first() ?? 'Não foi possível enviar.';
        }
    }

    /**
     * Existing 1:1 conversations with eligible peers (including inactive peers for history).
     *
     * @return Collection<int, array{conversation_id: int, user: User, online: bool, status_label: string, last_message: ?Message, unread: int, inactive: bool}>
     */
    #[Computed]
    public function conversations(): Collection
    {
        $actor = auth()->user();
        $eligibility = app(OperationalChatEligibility::class);
        $presence = app(UserPresence::class);
        $now = CarbonImmutable::now(config('app.timezone'));

        if (! $eligibility->canUseOperationalChat($actor)) {
            return collect();
        }

        $participations = ConversationParticipant::query()
            ->with([
                'conversation.messages' => fn ($q) => $q->latest('id')->limit(1),
                'conversation.participants.user:id,clinic_id,name,email,avatar_path,role,active,last_seen_at',
            ])
            ->where('clinic_id', $actor->clinic_id)
            ->where('user_id', $actor->id)
            ->get();

        $rows = collect();

        foreach ($participations as $participation) {
            $conversation = $participation->conversation;

            if ($conversation === null || $conversation->participants->count() !== 2) {
                continue;
            }

            $peerParticipant = $conversation->participants
                ->first(fn (ConversationParticipant $row): bool => (int) $row->user_id !== (int) $actor->id);

            $peer = $peerParticipant?->user;

            if ($peer === null || ! $eligibility->canViewConversationWith($actor, $peer)) {
                continue;
            }

            $lastMessage = $conversation->messages->first();

            if ($lastMessage === null) {
                continue;
            }

            $rows->push([
                'conversation_id' => $conversation->id,
                'user' => $peer,
                'online' => $presence->isOnline($peer, $now),
                'status_label' => $peer->active
                    ? $presence->statusLabel($peer, $now)
                    : 'Usuário inativo',
                'last_message' => $lastMessage,
                'unread' => app(ClinicMessageInbox::class)
                    ->unreadCountInConversation($participation, $actor, (int) $peer->id),
                'inactive' => ! $peer->active,
            ]);
        }

        return $rows
            ->sortByDesc(fn (array $row): int => $row['last_message']?->id ?? 0)
            ->values();
    }

    /**
     * Active ATTENDANT/SUPERVISOR peers available to start (or reopen) a conversation.
     *
     * @return Collection<int, array{user: User, online: bool, status_label: string}>
     */
    #[Computed]
    public function directoryPeers(): Collection
    {
        $actor = auth()->user();
        $eligibility = app(OperationalChatEligibility::class);
        $presence = app(UserPresence::class);
        $now = CarbonImmutable::now(config('app.timezone'));
        $search = trim($this->peerSearch);

        if (! $eligibility->canUseOperationalChat($actor)) {
            return collect();
        }

        $query = User::query()
            ->where('clinic_id', $actor->clinic_id)
            ->whereIn('role', $eligibility->eligibleRoleValues())
            ->where('active', true)
            ->whereKeyNot($actor->id)
            ->orderBy('name')
            ->orderBy('id');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        return $query
            ->get(['id', 'clinic_id', 'name', 'email', 'avatar_path', 'role', 'active', 'last_seen_at'])
            ->map(fn (User $peer): array => [
                'user' => $peer,
                'online' => $presence->isOnline($peer, $now),
                'status_label' => $presence->statusLabel($peer, $now),
            ])
            ->values();
    }

    #[Computed]
    public function activeConversation(): ?Conversation
    {
        if ($this->activeConversationId === null) {
            return null;
        }

        return $this->conversationForActor($this->activeConversationId);
    }

    #[Computed]
    public function peer(): ?User
    {
        $conversation = $this->activeConversation;

        if ($conversation === null) {
            return null;
        }

        return $this->peerForConversation($conversation);
    }

    #[Computed]
    public function peerOnline(): bool
    {
        return app(UserPresence::class)->isOnline($this->peer);
    }

    #[Computed]
    public function canSendToPeer(): bool
    {
        $actor = auth()->user();
        $peer = $this->peer;

        if ($actor === null || $peer === null) {
            return false;
        }

        return app(OperationalChatEligibility::class)->canChatWith($actor, $peer);
    }

    /**
     * @return EloquentCollection<int, Message>
     */
    #[Computed]
    public function messages(): EloquentCollection
    {
        $conversation = $this->activeConversation;

        if ($conversation === null) {
            return new EloquentCollection;
        }

        return Message::query()
            ->with('sender:id,name,avatar_path')
            ->where('conversation_id', $conversation->id)
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    public function render(): View
    {
        if ($this->activeConversation !== null) {
            $this->markRead($this->activeConversation);
        }

        return view('livewire.attendant-messages', [
            'presence' => app(UserPresence::class),
        ]);
    }

    private function conversationForActor(int $conversationId): Conversation
    {
        $user = auth()->user();
        $eligibility = app(OperationalChatEligibility::class);

        abort_unless($eligibility->canUseOperationalChat($user), 403);

        $isParticipant = ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user?->id)
            ->where('clinic_id', $user?->clinic_id)
            ->exists();

        abort_unless($isParticipant, 403);

        return Conversation::query()
            ->whereKey($conversationId)
            ->where('clinic_id', $user?->clinic_id)
            ->firstOrFail();
    }

    private function peerForConversation(Conversation $conversation): ?User
    {
        $user = auth()->user();

        return $conversation->participants()
            ->with('user:id,name,email,avatar_path,role,active,last_seen_at,clinic_id')
            ->where('user_id', '!=', $user?->id)
            ->where('clinic_id', $user?->clinic_id)
            ->first()
            ?->user;
    }

    private function markRead(Conversation $conversation): void
    {
        $user = auth()->user();
        $maxMessageId = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('clinic_id', $user?->clinic_id)
            ->max('id');

        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user?->id)
            ->where('clinic_id', $user?->clinic_id)
            ->update([
                'last_read_at' => now(),
                'last_read_message_id' => $maxMessageId,
            ]);
    }

    private function forgetComputed(): void
    {
        unset(
            $this->directoryPeers,
            $this->conversations,
            $this->messages,
            $this->activeConversation,
            $this->peer,
            $this->peerOnline,
            $this->canSendToPeer,
        );
    }
}
