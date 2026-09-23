<?php

namespace App\Actions;

use App\Models\Unit;
use App\Models\UnitQueuePolicy;
use App\Models\User;
use App\Services\UnitQueuePolicyResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RestoreUnitQueuePolicyDefaults
{
    public function __construct(
        private UnitQueuePolicyResolver $policyResolver,
    ) {}

    public function handle(User $actor, Unit $unit): UnitQueuePolicy
    {
        abort_if($unit->clinic_id !== $actor->clinic_id, 404);
        Gate::forUser($actor)->authorize('queue_policy.update');

        return DB::transaction(function () use ($unit): UnitQueuePolicy {
            return $this->policyResolver->persist($unit, $this->policyResolver->defaultAttributes($unit));
        });
    }
}
