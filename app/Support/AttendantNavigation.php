<?php

namespace App\Support;

use App\Models\User;
use App\Services\OperationalChatEligibility;

final class AttendantNavigation
{
    /**
     * @return list<array{label: string, icon: string, route: string, badge: int|null}>
     */
    public static function items(User $user, int $unreadMessages = 0): array
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
                'badge' => $unreadMessages > 0 ? $unreadMessages : null,
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
