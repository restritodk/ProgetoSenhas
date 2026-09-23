<?php

namespace App\Support;

class AdminNavigation
{
    /**
     * @return list<array{title: string, items: list<array{label: string, icon: string, route: string|null, available: bool}>}>
     */
    public static function sections(): array
    {
        return [
            [
                'title' => 'Visão geral',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard', 'available' => true],
                    ['label' => 'Clínica / Unidades', 'icon' => 'building', 'route' => 'clinic.show', 'available' => true],
                ],
            ],
            [
                'title' => 'Gestão',
                'items' => [
                    ['label' => 'Usuários', 'icon' => 'users', 'route' => 'users.index', 'available' => true],
                    ['label' => 'Perfis e Permissões', 'icon' => 'shield', 'route' => null, 'available' => false],
                    ['label' => 'Configurações', 'icon' => 'cog', 'route' => 'settings.index', 'available' => true],
                    ['label' => 'Logs de Auditoria', 'icon' => 'clipboard', 'route' => null, 'available' => false],
                ],
            ],
            [
                'title' => 'Operação',
                'items' => [
                    ['label' => 'Mesas / Guichês', 'icon' => 'desk', 'route' => 'desks.index', 'available' => true],
                    ['label' => 'Tipos de Senha', 'icon' => 'ticket', 'route' => 'ticket-types.index', 'available' => true],
                    ['label' => 'Tipos por Unidade', 'icon' => 'queue', 'route' => 'unit-ticket-types.index', 'available' => true],
                    ['label' => 'Emitir senha', 'icon' => 'hash', 'route' => 'tickets.issue', 'available' => true],
                    ['label' => 'Atendimento', 'icon' => 'heart', 'route' => 'attendant.panel', 'available' => true],
                    ['label' => 'Totens', 'icon' => 'monitor', 'route' => 'kiosks.index', 'available' => true],
                    ['label' => 'Filas e Prioridades', 'icon' => 'queue', 'route' => null, 'available' => false],
                    ['label' => 'Senhas', 'icon' => 'hash', 'route' => null, 'available' => false],
                    ['label' => 'Atendimentos', 'icon' => 'heart', 'route' => null, 'available' => false],
                ],
            ],
            [
                'title' => 'Comunicação',
                'items' => [
                    ['label' => 'Mídia da TV', 'icon' => 'image', 'route' => 'media-items.index', 'available' => true],
                    ['label' => 'Painéis / TVs', 'icon' => 'monitor', 'route' => 'display-panels.index', 'available' => true],
                    ['label' => 'Relatórios', 'icon' => 'chart', 'route' => null, 'available' => false],
                ],
            ],
        ];
    }
}
