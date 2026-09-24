<?php

namespace App\Support;

use App\Models\User;
use App\Services\OperationalChatEligibility;

final class AttendantNavigation
{
    /**
     * @return list<array{label: string, icon: string, route: string, badge: int|null, livewire_badge?: string}>
     */
    public static function items(User $user): array
    {
        $items = [];

        if ($user->canAccessAttendantPanel()) {
            $items[] = [
                'label' => 'Dashboard',
                'icon' => 'chart',
                'route' => 'attendant.dashboard',
                'badge' => null,
            ];
            $items[] = [
                'label' => 'Atendimento',
                'icon' => 'heart',
                'route' => 'attendant.panel',
                'badge' => null,
            ];
            $items[] = [
                'label' => 'Fila da Mesa',
                'icon' => 'queue',
                'route' => 'attendant.queue',
                'badge' => null,
            ];
            $items[] = [
                'label' => 'Histórico',
                'icon' => 'clock',
                'route' => 'attendant.history',
                'badge' => null,
            ];
        }

        if (app(OperationalChatEligibility::class)->canUseOperationalChat($user)) {
            $items[] = [
                'label' => 'Mensagens',
                'icon' => 'message',
                'route' => 'attendant.messages',
                // Badge is rendered by Livewire (AttendantMessagesBadge) so it
                // stays fresh on every attendant page without a full reload.
                'badge' => null,
                'livewire_badge' => 'messages',
            ];
        }

        $items[] = [
            'label' => 'Perfil',
            'icon' => 'user',
            'route' => 'attendant.profile',
            'badge' => null,
        ];

        return $items;
    }
}
