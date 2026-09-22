<?php

namespace App\Actions;

use App\Exceptions\LastAdministratorProtectedException;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UpdateUser
{
    public function __construct(private SyncUserUnits $syncUserUnits) {}

    /**
     * @param  array{name: string, email: string, role: UserRole, password?: string|null, active: bool, unit_ids: list<int>}  $attributes
     */
    public function handle(User $actor, User $target, array $attributes): User
    {
        abort_if($target->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('update', $target);

        return DB::transaction(function () use ($actor, $target, $attributes): User {
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->clinic_id !== $actor->clinic_id, 404);

            $role = $attributes['role'];
            $active = $attributes['active'];

            $this->guardLastAdministrator($actor, $locked, $role, $active);

            if ($locked->role !== $role) {
                Gate::forUser($actor)->authorize('assignRole', $locked);
            }

            $payload = [
                'name' => $attributes['name'],
                'email' => Str::lower($attributes['email']),
                'role' => $role,
                'active' => $active,
            ];

            if (filled($attributes['password'] ?? null)) {
                $payload['password'] = $attributes['password'];
            }

            $locked->forceFill($payload)->save();
            $this->syncUserUnits->handle($locked, $attributes['unit_ids'], $actor->clinic_id);

            return $locked->refresh();
        });
    }

    private function guardLastAdministrator(User $actor, User $target, UserRole $role, bool $active): void
    {
        $admins = User::query()
            ->where('clinic_id', $actor->clinic_id)
            ->where('role', UserRole::ADMINISTRATOR)
            ->where('active', true)
            ->lockForUpdate()
            ->get();

        $isLastActiveAdministrator = $admins->count() === 1 && $admins->first()?->is($target);

        if (! $isLastActiveAdministrator) {
            return;
        }

        if (! $active) {
            throw LastAdministratorProtectedException::cannotDeactivate();
        }

        if ($role !== UserRole::ADMINISTRATOR) {
            throw LastAdministratorProtectedException::cannotDemote();
        }
    }
}
