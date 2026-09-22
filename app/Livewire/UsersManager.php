<?php

namespace App\Livewire;

use App\Actions\CreateUser;
use App\Actions\UpdateUser;
use App\Exceptions\LastAdministratorProtectedException;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class UsersManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $role = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $active = true;

    /** @var list<int> */
    public array $unitIds = [];

    public ?int $userPendingDeactivationId = null;

    public string $statusMessage = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
        $this->role = UserRole::ATTENDANT->value;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->authorize('create', User::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        $user = $this->userForCurrentClinic($userId);
        $this->authorize('update', $user);

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->password = '';
        $this->password_confirmation = '';
        $this->active = $user->active;
        $this->unitIds = $user->units()->pluck('units.id')->map(fn ($id): int => (int) $id)->all();
        $this->showForm = true;
        $this->userPendingDeactivationId = null;
        $this->statusMessage = '';
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(CreateUser $createUser, UpdateUser $updateUser): void
    {
        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $this->email = Str::lower($this->email);
        $this->validate();

        $attributes = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => UserRole::from($this->role),
            'active' => $this->active,
            'unit_ids' => array_map(intval(...), $this->unitIds),
        ];

        try {
            if ($this->editingUserId !== null) {
                $target = $this->userForCurrentClinic($this->editingUserId);
                $attributes['password'] = $this->password !== '' ? $this->password : null;
                $updateUser->handle($actor, $target, $attributes);
                $message = 'Usuário atualizado com sucesso.';
            } else {
                $attributes['password'] = $this->password;
                $createUser->handle($actor, $attributes);
                $message = 'Usuário criado com sucesso.';
            }
        } catch (LastAdministratorProtectedException $exception) {
            $this->addError('role', $exception->getMessage());
            $this->addError('active', $exception->getMessage());

            return;
        }

        $this->resetForm();
        $this->statusMessage = $message;
        $this->resetPage();
    }

    public function confirmDeactivation(int $userId): void
    {
        $user = $this->userForCurrentClinic($userId);
        $this->authorize('update', $user);
        $this->userPendingDeactivationId = $user->id;
    }

    public function cancelDeactivation(): void
    {
        $this->userPendingDeactivationId = null;
    }

    public function deactivate(UpdateUser $updateUser): void
    {
        abort_if($this->userPendingDeactivationId === null, 404);

        $actor = auth()->user();
        $target = $this->userForCurrentClinic($this->userPendingDeactivationId);
        $this->authorize('update', $target);

        try {
            $updateUser->handle($actor, $target, [
                'name' => $target->name,
                'email' => $target->email,
                'role' => $target->role,
                'active' => false,
                'unit_ids' => $target->units()->pluck('units.id')->map(fn ($id): int => (int) $id)->all(),
            ]);
        } catch (LastAdministratorProtectedException $exception) {
            $this->userPendingDeactivationId = null;
            $this->addError('status', $exception->getMessage());

            return;
        }

        $this->userPendingDeactivationId = null;
        $this->statusMessage = 'Usuário desativado.';
        $this->resetPage();
    }

    public function activate(int $userId, UpdateUser $updateUser): void
    {
        $actor = auth()->user();
        $target = $this->userForCurrentClinic($userId);
        $this->authorize('update', $target);

        $updateUser->handle($actor, $target, [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role,
            'active' => true,
            'unit_ids' => $target->units()->pluck('units.id')->map(fn ($id): int => (int) $id)->all(),
        ]);

        $this->statusMessage = 'Usuário ativado.';
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function users(): LengthAwarePaginator
    {
        $clinicId = auth()->user()?->clinic_id;

        return User::query()
            ->with(['units:id,name,clinic_id'])
            ->where('clinic_id', $clinicId)
            ->when($this->search !== '', function ($query): void {
                $term = '%'.Str::lower($this->search).'%';
                $query->where(function ($search) use ($term): void {
                    $search->whereRaw('LOWER(name) like ?', [$term])
                        ->orWhereRaw('LOWER(email) like ?', [$term]);
                });
            })
            ->when($this->roleFilter !== '', fn ($query) => $query->where('role', $this->roleFilter))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('active', false))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10);
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function availableUnits(): Collection
    {
        return Unit::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'clinic_id', 'active']);
    }

    public function render(): View
    {
        return view('livewire.users-manager', [
            'users' => $this->users(),
            'roles' => UserRole::cases(),
            'activeAdministratorCount' => User::query()
                ->where('clinic_id', auth()->user()?->clinic_id)
                ->where('role', UserRole::ADMINISTRATOR)
                ->where('active', true)
                ->count(),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $passwordRules = ['nullable', 'string'];

        if ($this->editingUserId === null || $this->password !== '') {
            $passwordRules = [
                $this->editingUserId === null ? 'required' : 'nullable',
                'string',
                'confirmed',
                Password::min(8),
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                Rule::unique('users', 'email')->ignore($this->editingUserId),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => $passwordRules,
            'active' => ['boolean'],
            'unitIds' => ['array'],
            'unitIds.*' => [
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do usuário.',
            'email.required' => 'Informe o e-mail do usuário.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Já existe um usuário com este e-mail.',
            'role.required' => 'Selecione o perfil do usuário.',
            'password.required' => 'Informe a senha inicial.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'unitIds.*.exists' => 'Uma ou mais unidades não pertencem à sua clínica.',
        ];
    }

    private function userForCurrentClinic(int $userId): User
    {
        return User::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($userId)
            ->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingUserId = null;
        $this->showForm = false;
        $this->name = '';
        $this->email = '';
        $this->role = UserRole::ATTENDANT->value;
        $this->password = '';
        $this->password_confirmation = '';
        $this->active = true;
        $this->unitIds = [];
        $this->userPendingDeactivationId = null;
    }
}
