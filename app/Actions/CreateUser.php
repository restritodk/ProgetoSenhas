<?php

namespace App\Actions;

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateUser
{
    public function __construct(private SyncUserUnits $syncUserUnits) {}

    /**
     * @param  array{name: string, email: string, role: UserRole, password: string, active: bool, unit_ids: list<int>}  $attributes
     */
    public function handle(User $actor, array $attributes): User
    {
        Gate::forUser($actor)->authorize('create', User::class);
        abort_if($actor->clinic_id === null, 404);

        return DB::transaction(function () use ($actor, $attributes): User {
            $user = new User;
            $user->forceFill([
                'clinic_id' => $actor->clinic_id,
                'name' => $attributes['name'],
                'email' => Str::lower($attributes['email']),
                'password' => $attributes['password'],
                'role' => $attributes['role'],
                'active' => $attributes['active'],
            ])->save();

            $this->syncUserUnits->handle($user, $attributes['unit_ids'], $actor->clinic_id);

            return $user->refresh();
        });
    }
}
