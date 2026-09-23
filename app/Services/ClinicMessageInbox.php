<?php

namespace App\Services;

use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

class ClinicMessageInbox
{
    public function __construct(
        private OperationalChatEligibility $eligibility,
    ) {}

    /**
     * Unread messages from valid operational-chat peers only
     * (ATTENDANT/SUPERVISOR, same clinic). Legacy admin messages do not count.
     */
    public function unreadCountFor(User $user): int
    {
        if (! $this->eligibility->canUseOperationalChat($user)) {
            return 0;
        }

        return (int) $this->unreadByPeerId($user)->sum();
    }

    /**
     * @return Collection<int, int> peer_user_id => unread count
     */
    public function unreadByPeerId(User $user): Collection
    {
        if (! $this->eligibility->canUseOperationalChat($user)) {
            return collect();
        }

        $participations = ConversationParticipant::query()
            ->with(['conversation.participants.user:id,clinic_id,role,active'])
            ->where('clinic_id', $user->clinic_id)
            ->where('user_id', $user->id)
            ->get();

        $counts = collect();

        foreach ($participations as $participation) {
            $conversation = $participation->conversation;

            if ($conversation === null || $conversation->participants->count() !== 2) {
                continue;
            }

            $peerParticipant = $conversation->participants
                ->first(fn (ConversationParticipant $row): bool => (int) $row->user_id !== (int) $user->id);

            $peer = $peerParticipant?->user;

            if ($peer === null || ! $this->eligibility->canViewConversationWith($user, $peer)) {
                continue;
            }

            $unread = $this->unreadCountInConversation($participation, $user, (int) $peer->id);

            if ($unread > 0) {
                $counts->put((int) $peer->id, $unread);
            }
        }

        return $counts;
    }

    public function unreadCountInConversation(
        ConversationParticipant $participation,
        User $user,
        int $peerUserId,
    ): int {
        $query = Message::query()
            ->where('conversation_id', $participation->conversation_id)
            ->where('clinic_id', $user->clinic_id)
            ->where('sender_id', $peerUserId);

        if ($participation->last_read_message_id !== null) {
            $query->where('id', '>', (int) $participation->last_read_message_id);
        } elseif ($participation->last_read_at !== null) {
            // Legacy rows before last_read_message_id existed.
            $query->where('created_at', '>', $participation->last_read_at);
        }

        return (int) $query->count();
    }

    /**
     * @return list<string>
     */
    public function eligibleRoleValues(): array
    {
        return $this->eligibility->eligibleRoleValues();
    }
}
