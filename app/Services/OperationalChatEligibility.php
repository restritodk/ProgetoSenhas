<?php

namespace App\Services;

use App\Models\User;
use App\UserRole;

/**
 * Operational chat eligibility: ATTENDANT/SUPERVISOR within the same Clinic only.
 * ADMINISTRATOR never participates. Unit is intentionally not a chat boundary.
 */
class OperationalChatEligibility
{
    /**
     * @return list<UserRole>
     */
    public function eligibleRoles(): array
    {
        return [UserRole::ATTENDANT, UserRole::SUPERVISOR];
    }

    /**
     * @return list<string>
     */
    public function eligibleRoleValues(): array
    {
        return array_map(
            static fn (UserRole $role): string => $role->value,
            $this->eligibleRoles(),
        );
    }

    public function isEligibleRole(?UserRole $role): bool
    {
        return $role === UserRole::ATTENDANT || $role === UserRole::SUPERVISOR;
    }

    public function canUseOperationalChat(?User $user): bool
    {
        return $user !== null
            && $user->active
            && $user->clinic_id !== null
            && $this->isEligibleRole($user->role)
            && $user->hasPermission('messages.access');
    }

    /**
     * Start a new conversation or send a message to an active peer.
     */
    public function canChatWith(User $actor, User $peer): bool
    {
        return $this->canUseOperationalChat($actor)
            && (int) $peer->id !== (int) $actor->id
            && $peer->clinic_id !== null
            && (int) $peer->clinic_id === (int) $actor->clinic_id
            && $peer->active
            && $this->isEligibleRole($peer->role);
    }

    /**
     * View / keep history with a chat-role peer (active or inactive) in the same clinic.
     */
    public function canViewConversationWith(User $actor, User $peer): bool
    {
        return $this->canUseOperationalChat($actor)
            && (int) $peer->id !== (int) $actor->id
            && $peer->clinic_id !== null
            && (int) $peer->clinic_id === (int) $actor->clinic_id
            && $this->isEligibleRole($peer->role);
    }

    public function rejectionMessage(): string
    {
        return 'Só é possível conversar com atendentes e supervisores ativos da sua clínica.';
    }
}
