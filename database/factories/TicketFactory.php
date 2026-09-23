<?php

namespace Database\Factories;

use App\Actions\EnsureDefaultSectorForUnit;
use App\Models\Clinic;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\TicketSource;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ticket> */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'unit_id' => null,
            'sector_id' => null,
            'ticket_type_id' => null,
            'sequence_number' => 1,
            'sequence_date' => now(config('app.timezone'))->toDateString(),
            'status' => TicketStatus::WAITING,
            'source' => TicketSource::ADMIN,
            'issued_at' => now(config('app.timezone')),
            'queued_at' => now(config('app.timezone')),
            'called_at' => null,
            'service_started_at' => null,
            'completed_at' => null,
            'target_desk_id' => null,
            'kiosk_id' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Ticket $ticket): void {
            if ($ticket->clinic_id === null) {
                $ticket->clinic_id = Clinic::factory()->create()->id;
            }

            if ($ticket->unit_id === null) {
                $ticket->unit_id = Unit::factory()->create(['clinic_id' => $ticket->clinic_id])->id;
            }

            if ($ticket->sector_id === null && $ticket->unit_id !== null) {
                $unit = Unit::query()->find($ticket->unit_id);
                if ($unit !== null) {
                    $ticket->sector_id = app(EnsureDefaultSectorForUnit::class)->handle($unit)->id;
                }
            }

            if ($ticket->ticket_type_id === null) {
                $ticket->ticket_type_id = TicketType::factory()->create([
                    'clinic_id' => $ticket->clinic_id,
                    'prefix' => 'N',
                    'priority' => 10,
                ])->id;
            }
        });
    }

    public function waiting(): static
    {
        return $this->state(fn (): array => [
            'status' => TicketStatus::WAITING,
        ]);
    }
}
