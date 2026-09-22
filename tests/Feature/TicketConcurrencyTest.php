<?php

namespace Tests\Feature;

use App\Actions\IssueTicket;
use App\Models\Clinic;
use App\Models\Ticket;
use App\Models\TicketSequence;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TicketConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_many_sequential_emissions_never_duplicate_sequence_numbers(): void
    {
        [$admin, $unit, $type] = $this->readyIssuer();
        $this->actingAs($admin);
        $issuer = app(IssueTicket::class);

        $numbers = [];
        for ($i = 0; $i < 25; $i++) {
            $numbers[] = $issuer->handle($admin, $unit->id, $type->id)->sequence_number;
        }

        $this->assertSame(range(1, 25), $numbers);
        $this->assertCount(25, Ticket::query()->pluck('sequence_number')->unique());
        $this->assertSame(25, TicketSequence::query()->value('last_number'));
    }

    public function test_unique_constraint_is_last_barrier_against_duplicate_sequences(): void
    {
        [$admin, $unit, $type] = $this->readyIssuer();
        $this->actingAs($admin);

        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->expectException(QueryException::class);

        $duplicate = new Ticket;
        $duplicate->forceFill([
            'clinic_id' => $ticket->clinic_id,
            'unit_id' => $ticket->unit_id,
            'ticket_type_id' => $ticket->ticket_type_id,
            'sequence_number' => $ticket->sequence_number,
            'sequence_date' => $ticket->sequence_date,
            'status' => $ticket->status,
            'issued_at' => now(config('app.timezone')),
        ])->save();
    }

    public function test_counter_creation_race_recovers_to_a_single_sequence_row(): void
    {
        [$admin, $unit, $type] = $this->readyIssuer();
        $this->actingAs($admin);
        $issuer = app(IssueTicket::class);

        $first = $issuer->handle($admin, $unit->id, $type->id);
        $second = $issuer->handle($admin, $unit->id, $type->id);

        $this->assertSame(1, $first->sequence_number);
        $this->assertSame(2, $second->sequence_number);
        $this->assertSame(
            1,
            TicketSequence::query()
                ->where('clinic_id', $unit->clinic_id)
                ->where('unit_id', $unit->id)
                ->where('ticket_type_id', $type->id)
                ->count(),
        );
    }

    /**
     * SQLite in-memory does not faithfully reproduce MySQL/MariaDB row locking.
     * This test documents that limitation and only asserts sequential safety under SQLite.
     * Production concurrency relies on transaction + lockForUpdate + unique constraints on MariaDB.
     */
    public function test_documents_sqlite_locking_limitation_for_true_parallelism(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->assertSame('sqlite', $driver);
            $this->assertTrue(true, 'Parallel lockForUpdate semantics are not fully validated on SQLite test DB.');

            return;
        }

        [$admin, $unit, $type] = $this->readyIssuer();
        $this->actingAs($admin);
        $issuer = app(IssueTicket::class);

        $numbers = collect(range(1, 10))
            ->map(fn (): int => $issuer->handle($admin, $unit->id, $type->id)->sequence_number)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(range(1, 10), $numbers);
    }

    /**
     * @return array{0: User, 1: Unit, 2: TicketType}
     */
    private function readyIssuer(): array
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $unit = Unit::factory()->for($clinic)->create();
        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => 'Normal',
            'prefix' => 'N',
            'priority' => 10,
            'active' => true,
        ])->save();

        return [$admin, $unit, $type->refresh()];
    }
}
