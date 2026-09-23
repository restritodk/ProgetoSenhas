<?php

namespace App\Actions;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use App\Services\OperationalChatEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OpenDirectConversation
{
    public function __construct(
        private OperationalChatEligibility $eligibility,
    ) {}

    public function handle(User $actor, User $peer): Conversation
    {
        Gate::forUser($actor)->authorize('messages.access');

        if (! $this->eligibility->canChatWith($actor, $peer)) {
            throw ValidationException::withMessages([
                'peer' => $this->eligibility->rejectionMessage(),
            ]);
        }

        return DB::transaction(function () use ($actor, $peer): Conversation {
            // Stable lock order prevents duplicate 1:1 conversations under concurrency.
            User::query()
                ->whereKey([$actor->id, $peer->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $existing = $this->findDirectConversation($actor, $peer);

            if ($existing !== null) {
                return $existing;
            }

            $conversation = new Conversation;
            $conversation->forceFill([
                'clinic_id' => $actor->clinic_id,
            ])->save();

            foreach ([$actor, $peer] as $user) {
                $participant = new ConversationParticipant;
                $participant->forceFill([
                    'conversation_id' => $conversation->id,
                    'clinic_id' => $actor->clinic_id,
                    'user_id' => $user->id,
                    'last_read_at' => null,
                ])->save();
            }

            return $conversation->refresh();
        });
    }

    private function findDirectConversation(User $actor, User $peer): ?Conversation
    {
        $sharedIds = ConversationParticipant::query()
            ->select('conversation_id')
            ->where('clinic_id', $actor->clinic_id)
            ->where('user_id', $actor->id)
            ->whereIn('conversation_id', function ($query) use ($actor, $peer): void {
                $query->select('conversation_id')
                    ->from('conversation_participants')
                    ->where('clinic_id', $actor->clinic_id)
                    ->where('user_id', $peer->id);
            })
            ->pluck('conversation_id');

        foreach ($sharedIds as $conversationId) {
            $participantCount = ConversationParticipant::query()
                ->where('conversation_id', $conversationId)
                ->where('clinic_id', $actor->clinic_id)
                ->count();

            if ($participantCount === 2) {
                return Conversation::query()
                    ->whereKey($conversationId)
                    ->where('clinic_id', $actor->clinic_id)
                    ->first();
            }
        }

        return null;
    }
}
