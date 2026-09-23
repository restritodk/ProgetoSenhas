<?php

namespace App\Support;

use App\UserRole;

/**
 * Source of truth for permission keys, modules, defaults and dependencies.
 * Synced into the permissions table; clinic_role_permissions stores per-clinic grants.
 */
final class PermissionCatalog
{
    /**
     * @return list<array{
     *     key: string,
     *     module: string,
     *     name: string,
     *     description: string,
     *     depends_on: list<string>,
     *     sort: int
     * }>
     */
    public static function definitions(): array
    {
        $sort = 0;
        $def = static function (
            string $key,
            string $module,
            string $name,
            string $description,
            array $dependsOn = [],
        ) use (&$sort): array {
            $sort += 10;

            return [
                'key' => $key,
                'module' => $module,
                'name' => $name,
                'description' => $description,
                'depends_on' => $dependsOn,
                'sort' => $sort,
            ];
        };

        return [
            $def('dashboard.view', 'dashboard', 'Acessar dashboard', 'Permite abrir o painel administrativo inicial.'),

            $def('attendant.access', 'attendant', 'Acessar Painel do Atendente', 'Permite entrar no Painel do Atendente.'),
            $def('tickets.call', 'attendant', 'Chamar próxima senha', 'Permite selecionar a próxima senha da fila.', ['attendant.access']),
            $def('tickets.recall', 'attendant', 'Rechamar senha', 'Permite rechamar a senha atual.', ['attendant.access']),
            $def('tickets.start', 'attendant', 'Iniciar atendimento', 'Permite iniciar o atendimento da senha chamada.', ['attendant.access']),
            $def('tickets.complete', 'attendant', 'Finalizar atendimento', 'Permite concluir o atendimento.', ['attendant.access']),
            $def('tickets.no_show', 'attendant', 'Marcar não compareceu', 'Permite registrar não comparecimento.', ['attendant.access']),
            $def('tickets.transfer', 'attendant', 'Transferir senha', 'Permite transferir a senha para a fila ou outra mesa.', ['attendant.access']),

            $def('messages.access', 'messages', 'Mensagens internas', 'Permite usar o bate-papo operacional da clínica.'),

            $def('tickets.issue', 'tickets', 'Emitir senha manualmente', 'Permite emitir senhas pelo painel administrativo.'),

            $def('users.view', 'users', 'Visualizar usuários', 'Permite listar e consultar usuários da clínica.'),
            $def('users.create', 'users', 'Criar usuário', 'Permite cadastrar novos usuários.', ['users.view']),
            $def('users.update', 'users', 'Editar usuário', 'Permite alterar dados e vínculos de usuários.', ['users.view']),
            $def('users.manage_status', 'users', 'Ativar/desativar usuário', 'Permite ativar ou desativar usuários.', ['users.view']),
            $def('users.assign_role', 'users', 'Gerenciar perfil de usuário', 'Permite alterar o perfil (Administrador, Supervisor, Atendente).', ['users.view']),

            $def('roles.view', 'roles', 'Visualizar perfis e permissões', 'Permite abrir o módulo Perfis e Permissões.'),
            $def('roles.update', 'roles', 'Alterar permissões de perfil', 'Permite salvar alterações nas permissões dos perfis.', ['roles.view']),

            $def('desks.view', 'desks', 'Visualizar mesas', 'Permite listar mesas e guichês.'),
            $def('desks.create', 'desks', 'Criar mesa', 'Permite cadastrar novas mesas.', ['desks.view']),
            $def('desks.update', 'desks', 'Editar mesa', 'Permite editar mesas existentes.', ['desks.view']),
            $def('desks.manage_status', 'desks', 'Ativar/desativar mesa', 'Permite ativar ou desativar mesas.', ['desks.view']),

            $def('sectors.view', 'sectors', 'Visualizar setores', 'Permite listar setores das unidades.'),
            $def('sectors.create', 'sectors', 'Criar setor', 'Permite cadastrar novos setores.', ['sectors.view']),
            $def('sectors.update', 'sectors', 'Editar setor', 'Permite editar setores existentes.', ['sectors.view']),
            $def('sectors.manage_status', 'sectors', 'Ativar/desativar setor', 'Permite ativar ou desativar setores.', ['sectors.view']),

            $def('ticket_types.view', 'ticket_types', 'Visualizar tipos de senha', 'Permite listar tipos de senha.'),
            $def('ticket_types.create', 'ticket_types', 'Criar tipo de senha', 'Permite criar novos tipos.', ['ticket_types.view']),
            $def('ticket_types.update', 'ticket_types', 'Editar tipo de senha', 'Permite editar tipos existentes.', ['ticket_types.view']),
            $def('ticket_types.manage_status', 'ticket_types', 'Ativar/desativar tipo', 'Permite ativar ou desativar tipos.', ['ticket_types.view']),
            $def('ticket_types.delete', 'ticket_types', 'Excluir tipo de senha', 'Permite excluir tipos sem senhas históricas.', ['ticket_types.view']),
            $def('unit_ticket_types.manage', 'ticket_types', 'Gerenciar tipos por unidade', 'Permite configurar a oferta do Totem por unidade.', ['ticket_types.view']),

            $def('queue_policy.view', 'queue_policy', 'Visualizar regras de fila', 'Permite abrir Filas e Prioridades.'),
            $def('queue_policy.update', 'queue_policy', 'Alterar regras de fila', 'Permite salvar e restaurar políticas de chamada.', ['queue_policy.view']),

            $def('kiosks.view', 'kiosks', 'Visualizar Totens', 'Permite listar totens da clínica.'),
            $def('kiosks.create', 'kiosks', 'Criar Totem', 'Permite cadastrar totens.', ['kiosks.view']),
            $def('kiosks.update', 'kiosks', 'Editar Totem', 'Permite editar e configurar totens.', ['kiosks.view']),
            $def('kiosks.manage_status', 'kiosks', 'Ativar/desativar Totem', 'Permite ativar ou desativar totens.', ['kiosks.view']),
            $def('kiosks.regenerate_token', 'kiosks', 'Regenerar acesso do Totem', 'Permite regenerar o token/URL pública do totem.', ['kiosks.view']),

            $def('display_panels.view', 'display_panels', 'Visualizar painéis/TVs', 'Permite listar painéis da TV.'),
            $def('display_panels.create', 'display_panels', 'Criar painel/TV', 'Permite cadastrar painéis.', ['display_panels.view']),
            $def('display_panels.update', 'display_panels', 'Editar painel/TV', 'Permite editar painéis e playlist.', ['display_panels.view']),
            $def('display_panels.manage_status', 'display_panels', 'Ativar/desativar painel', 'Permite ativar ou desativar painéis.', ['display_panels.view']),
            $def('display_panels.regenerate_token', 'display_panels', 'Regenerar acesso do painel', 'Permite regenerar o token/URL do painel.', ['display_panels.view']),

            $def('media.view', 'media', 'Visualizar mídia da TV', 'Permite listar mídias.'),
            $def('media.create', 'media', 'Criar mídia', 'Permite enviar novas mídias.', ['media.view']),
            $def('media.update', 'media', 'Editar mídia', 'Permite editar mídias e vínculos com painéis.', ['media.view']),
            $def('media.delete', 'media', 'Excluir mídia', 'Permite excluir mídias.', ['media.view']),
            $def('media.manage_status', 'media', 'Ativar/desativar mídia', 'Permite ativar ou desativar mídias.', ['media.view']),

            $def('clinic.view', 'clinic', 'Visualizar clínica/unidades', 'Permite abrir a página da clínica e unidades.'),
            $def('clinic.update', 'clinic', 'Editar clínica', 'Permite alterar dados da clínica.', ['clinic.view']),
            $def('units.manage', 'clinic', 'Gerenciar unidades', 'Permite criar, editar e ativar/desativar unidades.', ['clinic.view']),

            $def('settings.view', 'settings', 'Visualizar configurações', 'Permite abrir as configurações da clínica.'),
            $def('settings.update', 'settings', 'Alterar configurações', 'Permite salvar branding, Totem, TV e demais ajustes.', ['settings.view']),
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::definitions(), 'key');
    }

    /**
     * @return array<string, array{key: string, module: string, name: string, description: string, depends_on: list<string>, sort: int}>
     */
    public static function keyed(): array
    {
        $map = [];
        foreach (self::definitions() as $definition) {
            $map[$definition['key']] = $definition;
        }

        return $map;
    }

    /**
     * @return array<string, array{key: string, label: string, description: string}>
     */
    public static function modules(): array
    {
        return [
            'dashboard' => ['key' => 'dashboard', 'label' => 'Dashboard', 'description' => 'Acesso ao painel inicial.'],
            'attendant' => ['key' => 'attendant', 'label' => 'Atendimento', 'description' => 'Operações do Painel do Atendente.'],
            'messages' => ['key' => 'messages', 'label' => 'Mensagens', 'description' => 'Bate-papo interno da clínica.'],
            'tickets' => ['key' => 'tickets', 'label' => 'Senhas', 'description' => 'Emissão administrativa de senhas.'],
            'users' => ['key' => 'users', 'label' => 'Usuários', 'description' => 'Gestão de usuários da clínica.'],
            'roles' => ['key' => 'roles', 'label' => 'Perfis e Permissões', 'description' => 'Configuração dos níveis de acesso.'],
            'desks' => ['key' => 'desks', 'label' => 'Mesas / Guichês', 'description' => 'Gestão de mesas de atendimento.'],
            'sectors' => ['key' => 'sectors', 'label' => 'Setores', 'description' => 'Setores operacionais dentro de cada unidade.'],
            'ticket_types' => ['key' => 'ticket_types', 'label' => 'Tipos de Senha', 'description' => 'Tipos e oferta por unidade.'],
            'queue_policy' => ['key' => 'queue_policy', 'label' => 'Filas e Prioridades', 'description' => 'Regras de chamada e distribuição.'],
            'kiosks' => ['key' => 'kiosks', 'label' => 'Totens', 'description' => 'Administração de totens.'],
            'display_panels' => ['key' => 'display_panels', 'label' => 'Painéis / TVs', 'description' => 'Administração de painéis públicos.'],
            'media' => ['key' => 'media', 'label' => 'Mídia da TV', 'description' => 'Biblioteca de mídia para a TV.'],
            'clinic' => ['key' => 'clinic', 'label' => 'Clínica / Unidades', 'description' => 'Dados da clínica e unidades.'],
            'settings' => ['key' => 'settings', 'label' => 'Configurações', 'description' => 'Configurações gerais e de marca.'],
        ];
    }

    /**
     * Defaults matching today's hardcoded RBAC so migration does not silently reduce access.
     *
     * @return list<string>
     */
    public static function defaultsFor(UserRole $role): array
    {
        return match ($role) {
            UserRole::ADMINISTRATOR => self::keys(),
            UserRole::SUPERVISOR, UserRole::ATTENDANT => [
                'dashboard.view',
                'attendant.access',
                'tickets.call',
                'tickets.recall',
                'tickets.start',
                'tickets.complete',
                'tickets.no_show',
                'tickets.transfer',
                'messages.access',
            ],
        };
    }

    /**
     * Permissions that cannot be removed from the Administrator profile (lockout protection).
     *
     * @return list<string>
     */
    public static function protectedAdministratorKeys(): array
    {
        return [
            'roles.view',
            'roles.update',
            'users.view',
            'users.create',
            'users.update',
            'users.manage_status',
            'users.assign_role',
            'clinic.view',
            'dashboard.view',
        ];
    }

    /**
     * Expand selected keys with required dependencies (transitive).
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function withDependencies(array $keys): array
    {
        $catalog = self::keyed();
        $resolved = [];
        $queue = array_values(array_unique($keys));

        while ($queue !== []) {
            $key = array_shift($queue);
            if ($key === null || isset($resolved[$key]) || ! isset($catalog[$key])) {
                continue;
            }

            $resolved[$key] = true;

            foreach ($catalog[$key]['depends_on'] as $dependency) {
                if (! isset($resolved[$dependency])) {
                    $queue[] = $dependency;
                }
            }
        }

        return array_keys($resolved);
    }

    /**
     * Keys that directly or transitively depend on the given permission.
     *
     * @return list<string>
     */
    public static function dependentsOf(string $permissionKey): array
    {
        $result = [];
        $queue = [$permissionKey];
        $seen = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === null || isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            foreach (self::definitions() as $definition) {
                if (! in_array($current, $definition['depends_on'], true)) {
                    continue;
                }

                $dependent = $definition['key'];
                if (! isset($result[$dependent])) {
                    $result[$dependent] = true;
                    $queue[] = $dependent;
                }
            }
        }

        return array_keys($result);
    }

    public static function roleDescription(UserRole $role): string
    {
        return match ($role) {
            UserRole::ADMINISTRATOR => 'Controle administrativo da clínica e recuperação de acesso.',
            UserRole::SUPERVISOR => 'Supervisão operacional e acompanhamento do atendimento.',
            UserRole::ATTENDANT => 'Operações de atendimento em mesas e guichês.',
        };
    }

    public static function roleAccessSummary(UserRole $role): string
    {
        return match ($role) {
            UserRole::ADMINISTRATOR => 'Acesso administrativo completo por padrão',
            UserRole::SUPERVISOR => 'Acesso operacional ao atendimento',
            UserRole::ATTENDANT => 'Acesso às operações de atendimento',
        };
    }
}
