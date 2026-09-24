<?php

namespace App\Actions;

use App\Models\Desk;
use App\Models\DeskAssignment;
use App\Models\User;
use App\Services\OperationalContext;
use App\Support\DeskLease;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimDesk
{
    public function __construct(private OperationalContext $operationalContext) {}

    public function handle(User $actor, Desk $desk): DeskAssignment
    {
        abort_if($actor->clinic_id === null, 404);

        $unit = $this->operationalContext->activeUnit($actor, session());
        abort_if($unit === null, 403);

        if (
            ! $desk->active
            || $desk->clinic_id !== $actor->clinic_id
            || $desk->unit_id !== $unit->id
            || ! $actor->canOperateUnit($unit)
        ) {
            throw ValidationException::withMessages([
                'deskId' => 'A mesa selecionada é inválida para o contexto operacional atual.',
            ]);
        }

        return DB::transaction(function () use ($actor, $desk, $unit): DeskAssignment {
            // Release any other desk this user still holds (switch desk).
            DeskAssignment::query()
                ->where('user_id', $actor->id)
                ->where('desk_id', '!=', $desk->id)
                ->delete();

            $existing = DeskAssignment::query()
                ->where('desk_id', $desk->id)
                ->lockForUpdate()
                ->first();

            // Abandoned lease: purge atomically under the same lock, then reclaim.
            // Tickets (CALLED / IN_SERVICE) are never mutated here.
            if ($existing !== null && ! DeskLease::isActive($existing)) {
                $existing->delete();
                $existing = null;
            }

            if ($existing !== null && (int) $existing->user_id !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'deskId' => 'Esta mesa acabou de ser ocupada por outro atendente. Selecione outra mesa disponível.',
                ]);
            }

            if ($existing === null) {
                $existing = new DeskAssignment;
                $existing->forceFill([
                    'clinic_id' => $actor->clinic_id,
                    'unit_id' => $unit->id,
                    'desk_id' => $desk->id,
                    'user_id' => $actor->id,
                    'claimed_at' => now(),
                    'last_seen_at' => now(),
                ])->save();
            } else {
                $existing->forceFill([
                    'last_seen_at' => now(),
                ])->save();
            }

            $this->operationalContext->setActiveDesk($actor, $desk, session());

            return $existing->refresh();
        });
    }
}
