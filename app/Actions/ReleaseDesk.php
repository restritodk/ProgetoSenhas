<?php

namespace App\Actions;

use App\Models\Desk;
use App\Models\DeskAssignment;
use App\Models\User;
use App\Services\OperationalContext;

class ReleaseDesk
{
    public function __construct(private OperationalContext $operationalContext) {}

    public function handle(User $actor, ?Desk $desk = null): void
    {
        $query = DeskAssignment::query()->where('user_id', $actor->id);

        if ($desk !== null) {
            $query->where('desk_id', $desk->id);
        }

        $query->delete();
        $this->operationalContext->clearDesk(session());
    }
}
