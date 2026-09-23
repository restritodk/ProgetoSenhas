<?php

namespace App\Livewire;

use App\Actions\SaveClinicRolePermissions;
use App\Models\User;
use App\Services\ClinicPermissionResolver;
use App\Support\PermissionCatalog;
use App\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RolePermissionsManager extends Component
{
    public ?string $editingRole = null;

    /** @var list<string> */
    public array $selectedPermissions = [];

    /** @var list<string> */
    public array $originalPermissions = [];

    /**
     * Snapshot used to detect additions/removals from wire:model checkbox binding.
     *
     * @var list<string>
     */
    public array $permissionsSnapshot = [];

    public string $search = '';

    /** @var array<string, bool> */
    public array $expandedModules = [];

    public bool $showSaveConfirm = false;

    public bool $showDiscardConfirm = false;

    public bool $showUncheckDependentsConfirm = false;

    public ?string $pendingUncheckKey = null;

    /** @var list<string> */
    public array $pendingCascadeKeys = [];

    public string $pendingNavigation = '';

    public string $statusMessage = '';

    public string $errorMessage = '';

    public bool $saving = false;

    public function mount(): void
    {
        $this->authorize('roles.view');
        $this->expandAll();
    }

    public function clearStatusMessage(): void
    {
        $this->statusMessage = '';
        $this->errorMessage = '';
    }

    public function editRole(string $role, ClinicPermissionResolver $resolver): void
    {
        $userRole = UserRole::tryFrom($role);
        abort_if($userRole === null, 404);
        $this->authorize('roles.view');

        if ($this->isDirty()) {
            $this->pendingNavigation = 'edit:'.$role;
            $this->showDiscardConfirm = true;

            return;
        }

        $this->loadRole($userRole, $resolver);
    }

    public function backToList(): void
    {
        if ($this->isDirty()) {
            $this->pendingNavigation = 'list';
            $this->showDiscardConfirm = true;

            return;
        }

        $this->resetEditor();
    }

    public function confirmDiscard(): void
    {
        $pending = $this->pendingNavigation;
        $this->showDiscardConfirm = false;
        $this->pendingNavigation = '';

        if ($pending === 'list') {
            $this->resetEditor();

            return;
        }

        if (str_starts_with($pending, 'edit:')) {
            $role = UserRole::tryFrom(substr($pending, 5));
            if ($role !== null) {
                $this->loadRole($role, app(ClinicPermissionResolver::class));
            }
        }
    }

    public function cancelDiscard(): void
    {
        $this->showDiscardConfirm = false;
        $this->pendingNavigation = '';
    }

    public function discardChanges(ClinicPermissionResolver $resolver): void
    {
        if ($this->editingRole === null) {
            return;
        }

        $role = UserRole::from($this->editingRole);
        $this->loadRole($role, $resolver);
        $this->statusMessage = '';
        $this->errorMessage = '';
    }

    /**
     * Livewire checkbox array binding entry-point.
     * Applies dependencies, protects Administrator keys, and confirms cascade unchecks.
     */
    public function updatedSelectedPermissions(): void
    {
        if ($this->editingRole === null) {
            return;
        }

        $this->authorize('roles.update');

        $selected = $this->normalizePermissionKeys($this->selectedPermissions);
        $previous = $this->normalizePermissionKeys($this->permissionsSnapshot);

        // Disabled protected checkboxes are omitted from browser form sync.
        // Restore them before interpreting the user's intent so real toggles still work.
        $protectedDropped = [];
        if ($this->editingRole === UserRole::ADMINISTRATOR->value) {
            foreach (PermissionCatalog::protectedAdministratorKeys() as $protected) {
                if (in_array($protected, $previous, true) && ! in_array($protected, $selected, true)) {
                    $protectedDropped[] = $protected;
                    $selected[] = $protected;
                }
            }
            $selected = $this->normalizePermissionKeys($selected);
        }

        $removed = array_values(array_diff($previous, $selected));
        $added = array_values(array_diff($selected, $previous));

        // Intentional attempt to drop only protected keys (e.g. programmatic set).
        if ($removed === [] && $added === [] && $protectedDropped !== []) {
            $this->errorMessage = 'Esta permissão é necessária para manter o acesso administrativo da clínica.';
            $this->syncSelection($selected);

            return;
        }

        foreach ($removed as $key) {
            $dependentsStillSelected = array_values(array_intersect(
                PermissionCatalog::dependentsOf($key),
                $selected,
            ));

            if ($dependentsStillSelected === []) {
                continue;
            }

            $selected[] = $key;
            $this->pendingUncheckKey = $key;
            $this->pendingCascadeKeys = $dependentsStillSelected;
            $this->showUncheckDependentsConfirm = true;
            $this->errorMessage = '';
            $this->syncSelection($selected);

            return;
        }

        if ($added !== []) {
            $selected = PermissionCatalog::withDependencies($selected);
        }

        $this->errorMessage = '';
        $this->syncSelection($selected);
    }

    public function confirmUncheckDependents(): void
    {
        $this->authorize('roles.update');

        $key = $this->pendingUncheckKey;
        if ($key === null || $this->isProtected($key)) {
            $this->cancelUncheckDependents();

            return;
        }

        $remove = array_values(array_unique([
            $key,
            ...$this->pendingCascadeKeys,
            ...PermissionCatalog::dependentsOf($key),
        ]));

        $selected = array_values(array_filter(
            $this->normalizePermissionKeys($this->selectedPermissions),
            fn (string $item): bool => ! in_array($item, $remove, true),
        ));

        $this->showUncheckDependentsConfirm = false;
        $this->pendingUncheckKey = null;
        $this->pendingCascadeKeys = [];
        $this->errorMessage = '';
        $this->syncSelection($selected);
    }

    public function cancelUncheckDependents(): void
    {
        $this->showUncheckDependentsConfirm = false;
        $this->pendingUncheckKey = null;
        $this->pendingCascadeKeys = [];
        $this->syncSelection($this->normalizePermissionKeys($this->selectedPermissions));
    }

    public function selectModule(string $module): void
    {
        $this->authorize('roles.update');
        $keys = collect(PermissionCatalog::definitions())
            ->where('module', $module)
            ->pluck('key')
            ->all();

        $this->syncSelection(PermissionCatalog::withDependencies(array_values(array_unique([
            ...$this->normalizePermissionKeys($this->selectedPermissions),
            ...$keys,
        ]))));
        $this->errorMessage = '';
    }

    public function clearModule(string $module): void
    {
        $this->authorize('roles.update');
        $moduleKeys = collect(PermissionCatalog::definitions())
            ->where('module', $module)
            ->pluck('key')
            ->all();

        $remaining = array_values(array_filter(
            $this->normalizePermissionKeys($this->selectedPermissions),
            function (string $key) use ($moduleKeys): bool {
                if ($this->isProtected($key)) {
                    return true;
                }

                return ! in_array($key, $moduleKeys, true);
            },
        ));

        // Drop orphans that depended on cleared module keys.
        $remaining = array_values(array_filter(
            $remaining,
            function (string $key) use ($remaining): bool {
                $definition = PermissionCatalog::keyed()[$key] ?? null;
                if ($definition === null) {
                    return false;
                }

                foreach ($definition['depends_on'] as $dependency) {
                    if (! in_array($dependency, $remaining, true)) {
                        return false;
                    }
                }

                return true;
            },
        ));

        $this->syncSelection(PermissionCatalog::withDependencies($remaining));
        $this->errorMessage = '';
    }

    public function expandAll(): void
    {
        foreach (array_keys(PermissionCatalog::modules()) as $module) {
            $this->expandedModules[$module] = true;
        }
    }

    public function collapseAll(): void
    {
        foreach (array_keys(PermissionCatalog::modules()) as $module) {
            $this->expandedModules[$module] = false;
        }
    }

    public function toggleModule(string $module): void
    {
        $this->expandedModules[$module] = ! ($this->expandedModules[$module] ?? false);
    }

    public function confirmSave(): void
    {
        $this->authorize('roles.update');

        if (! $this->isDirty()) {
            return;
        }

        $this->showSaveConfirm = true;
    }

    public function cancelSave(): void
    {
        $this->showSaveConfirm = false;
    }

    public function save(SaveClinicRolePermissions $saveClinicRolePermissions): void
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null || $this->editingRole === null, 404);
        $this->authorize('roles.update');

        $role = UserRole::from($this->editingRole);
        $clinic = $actor->clinic;
        abort_if($clinic === null, 404);

        $this->saving = true;
        $this->showSaveConfirm = false;
        $this->clearStatusMessage();

        try {
            $saved = $saveClinicRolePermissions->handle(
                $actor,
                $clinic,
                $role,
                $this->normalizePermissionKeys($this->selectedPermissions),
            );

            $this->syncSelection($saved);
            $this->originalPermissions = $saved;
            $this->statusMessage = 'Permissões do perfil "'.$role->label().'" atualizadas com sucesso.';
        } catch (ValidationException $exception) {
            $this->errorMessage = collect($exception->errors())->flatten()->first()
                ?? 'Não foi possível salvar as permissões. Revise os campos e tente novamente.';
            throw $exception;
        } finally {
            $this->saving = false;
        }
    }

    /**
     * @return Collection<int, array{role: UserRole, users_count: int, description: string, summary: string}>
     */
    #[Computed]
    public function roleCards(): Collection
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $counts = User::query()
            ->where('clinic_id', $actor->clinic_id)
            ->selectRaw('role, count(*) as aggregate')
            ->groupBy('role')
            ->pluck('aggregate', 'role');

        return collect(UserRole::cases())->map(fn (UserRole $role): array => [
            'role' => $role,
            'users_count' => (int) ($counts[$role->value] ?? 0),
            'description' => PermissionCatalog::roleDescription($role),
            'summary' => PermissionCatalog::roleAccessSummary($role),
        ]);
    }

    /**
     * @return array{profiles: int, active_users: int, attendants: int, administrators: int}
     */
    #[Computed]
    public function summary(): array
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        return [
            'profiles' => count(UserRole::cases()),
            'active_users' => User::query()->where('clinic_id', $actor->clinic_id)->where('active', true)->count(),
            'attendants' => User::query()->where('clinic_id', $actor->clinic_id)->where('role', UserRole::ATTENDANT)->count(),
            'administrators' => User::query()->where('clinic_id', $actor->clinic_id)->where('role', UserRole::ADMINISTRATOR)->count(),
        ];
    }

    public function isDirty(): bool
    {
        $current = $this->normalizePermissionKeys($this->selectedPermissions);
        $original = $this->normalizePermissionKeys($this->originalPermissions);
        sort($current);
        sort($original);

        return $this->editingRole !== null && $current !== $original;
    }

    public function isProtected(string $key): bool
    {
        return $this->editingRole === UserRole::ADMINISTRATOR->value
            && in_array($key, PermissionCatalog::protectedAdministratorKeys(), true);
    }

    public function pendingUncheckLabel(): string
    {
        if ($this->pendingUncheckKey === null) {
            return '';
        }

        return PermissionCatalog::keyed()[$this->pendingUncheckKey]['name'] ?? $this->pendingUncheckKey;
    }

    /**
     * @return list<string>
     */
    public function pendingCascadeLabels(): array
    {
        $catalog = PermissionCatalog::keyed();

        return array_values(array_map(
            fn (string $key): string => $catalog[$key]['name'] ?? $key,
            $this->pendingCascadeKeys,
        ));
    }

    public function render(): View
    {
        $modules = PermissionCatalog::modules();
        $definitions = collect(PermissionCatalog::definitions());

        if (trim($this->search) !== '') {
            $term = mb_strtolower(trim($this->search));
            $definitions = $definitions->filter(function (array $definition) use ($term, $modules): bool {
                $moduleLabel = mb_strtolower($modules[$definition['module']]['label'] ?? '');

                return str_contains(mb_strtolower($definition['name']), $term)
                    || str_contains(mb_strtolower($definition['description']), $term)
                    || str_contains(mb_strtolower($definition['module']), $term)
                    || str_contains($moduleLabel, $term)
                    || str_contains(mb_strtolower($definition['key']), $term);
            });
        }

        $grouped = $definitions->groupBy('module');

        return view('livewire.role-permissions-manager', [
            'groupedPermissions' => $grouped,
            'modulesMeta' => $modules,
            'usersUrl' => route('users.index'),
            'canUpdate' => auth()->user()?->hasPermission('roles.update') ?? false,
        ]);
    }

    private function loadRole(UserRole $role, ClinicPermissionResolver $resolver): void
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $keys = $this->normalizePermissionKeys($resolver->keysForRole((int) $actor->clinic_id, $role));
        $this->editingRole = $role->value;
        $this->syncSelection($keys);
        $this->originalPermissions = $keys;
        $this->search = '';
        $this->errorMessage = '';
        $this->showUncheckDependentsConfirm = false;
        $this->pendingUncheckKey = null;
        $this->pendingCascadeKeys = [];
        $this->expandAll();
        unset($this->roleCards, $this->summary);
    }

    private function resetEditor(): void
    {
        $this->editingRole = null;
        $this->selectedPermissions = [];
        $this->originalPermissions = [];
        $this->permissionsSnapshot = [];
        $this->search = '';
        $this->errorMessage = '';
        $this->showUncheckDependentsConfirm = false;
        $this->pendingUncheckKey = null;
        $this->pendingCascadeKeys = [];
        unset($this->roleCards, $this->summary);
    }

    /**
     * @param  list<mixed>  $keys
     * @return list<string>
     */
    private function normalizePermissionKeys(array $keys): array
    {
        $valid = PermissionCatalog::keys();

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $key): string => (string) $key, $keys),
            static fn (string $key): bool => in_array($key, $valid, true),
        )));
    }

    /**
     * @param  list<string>  $keys
     */
    private function syncSelection(array $keys): void
    {
        $normalized = $this->normalizePermissionKeys($keys);

        if ($this->editingRole === UserRole::ADMINISTRATOR->value) {
            foreach (PermissionCatalog::protectedAdministratorKeys() as $protected) {
                if (! in_array($protected, $normalized, true)) {
                    $normalized[] = $protected;
                }
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        $this->selectedPermissions = $normalized;
        $this->permissionsSnapshot = $normalized;
    }
}
