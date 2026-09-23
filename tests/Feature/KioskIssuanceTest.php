<?php

namespace Tests\Feature;

use App\Actions\IssueTicket;
use App\Actions\SyncUnitTicketTypes;
use App\Livewire\PublicKiosk;
use App\Models\Clinic;
use App\Models\Kiosk;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\TicketSource;
use App\TicketStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class KioskIssuanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_kiosk_issues_waiting_ticket_with_source_and_shared_sequence(): void
    {
        [$kiosk, $clinic, $unit, $admin, $types] = $this->ready();

        $adminTicket = app(IssueTicket::class)->handle($admin, $unit->id, $types['normal']->id);
        $this->assertSame(1, $adminTicket->sequence_number);
        $this->assertSame(TicketSource::ADMIN, $adminTicket->source);
        $this->assertNull($adminTicket->kiosk_id);

        $kioskTicket = app(IssueTicket::class)->handleFromKiosk(
            $kiosk,
            $types['normal']->id,
            'token-shared-sequence-0001',
            '127.0.0.1',
        );

        $this->assertSame(TicketStatus::WAITING, $kioskTicket->status);
        $this->assertSame($clinic->id, $kioskTicket->clinic_id);
        $this->assertSame($unit->id, $kioskTicket->unit_id);
        $this->assertSame($types['normal']->id, $kioskTicket->ticket_type_id);
        $this->assertSame(2, $kioskTicket->sequence_number);
        $this->assertSame('N002', $kioskTicket->display_code);
        $this->assertNotNull($kioskTicket->issued_at);
        $this->assertTrue($kioskTicket->queued_at->equalTo($kioskTicket->issued_at));
        $this->assertNull($kioskTicket->target_desk_id);
        $this->assertSame(TicketSource::KIOSK, $kioskTicket->source);
        $this->assertSame($kiosk->id, $kioskTicket->kiosk_id);
    }

    public function test_idempotent_request_token_does_not_create_second_ticket(): void
    {
        [$kiosk, , , , $types] = $this->ready();
        $issuer = app(IssueTicket::class);

        $first = $issuer->handleFromKiosk($kiosk, $types['preferential']->id, 'same-token-abcdefghijklmnopqrst', '10.0.0.1');
        $second = $issuer->handleFromKiosk($kiosk, $types['preferential']->id, 'same-token-abcdefghijklmnopqrst', '10.0.0.1');

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Ticket::query()->count());

        $third = $issuer->handleFromKiosk($kiosk, $types['preferential']->id, 'other-token-abcdefghijklmnopqrst', '10.0.0.1');
        $this->assertFalse($third->is($first));
        $this->assertSame(2, Ticket::query()->count());
        $this->assertSame(2, $third->sequence_number);
    }

    public function test_unauthorized_and_cross_tenant_ticket_types_are_rejected(): void
    {
        [$kiosk, $clinic, , $admin, $types] = $this->ready();
        $foreignClinic = Clinic::factory()->create();
        $foreignType = $this->type($foreignClinic, 'Alien', 'X', 10);

        try {
            app(IssueTicket::class)->handleFromKiosk($kiosk, $types['emergency']->id, 'token-emergency-disabled-01', '1.1.1.1');
            $this->fail('Inactive offer must be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            app(IssueTicket::class)->handleFromKiosk($kiosk, $foreignType->id, 'token-foreign-type-00000001', '1.1.1.1');
            $this->fail('Foreign type must be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(0, Ticket::query()->where('clinic_id', $clinic->id)->where('source', TicketSource::KIOSK)->count());
        $this->assertSame($admin->clinic_id, $clinic->id);
    }

    public function test_inactive_kiosk_cannot_issue(): void
    {
        [$kiosk, , , , $types] = $this->ready();
        $kiosk->forceFill(['active' => false])->save();

        $this->expectException(ValidationException::class);
        app(IssueTicket::class)->handleFromKiosk($kiosk->fresh(), $types['normal']->id, 'token-inactive-kiosk-000001', '2.2.2.2');
    }

    public function test_livewire_double_issue_with_same_token_emits_once(): void
    {
        [$kiosk, , , , $types] = $this->ready();

        $component = Livewire::test(PublicKiosk::class, ['publicToken' => $kiosk->public_token]);
        $token = $component->get('requestToken');

        $component
            ->set('requestToken', $token)
            ->call('issue', $types['normal']->id)
            ->assertSet('screen', 'result')
            ->assertSee('N001');

        // Simulate duplicated request with the same issuance token before refresh.
        app(IssueTicket::class)->handleFromKiosk($kiosk, $types['normal']->id, $token, '127.0.0.1');

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_rate_limit_blocks_excessive_issuance_attempts(): void
    {
        [$kiosk, , , , $types] = $this->ready();
        RateLimiter::clear('kiosk-issue:'.$kiosk->id.':9.9.9.9');

        $issuer = app(IssueTicket::class);

        for ($i = 0; $i < 60; $i++) {
            $issuer->handleFromKiosk(
                $kiosk,
                $types['normal']->id,
                sprintf('rate-limit-token-%032d', $i),
                '9.9.9.9',
            );
        }

        $this->expectException(TooManyRequestsHttpException::class);
        $issuer->handleFromKiosk(
            $kiosk,
            $types['normal']->id,
            sprintf('rate-limit-token-%032d', 60),
            '9.9.9.9',
        );
    }

    /**
     * @return array{0: Kiosk, 1: Clinic, 2: Unit, 3: User, 4: array{normal: TicketType, preferential: TicketType, emergency: TicketType}}
     */
    private function ready(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Centro']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $normal = $this->type($clinic, 'Normal', 'N', 10);
        $preferential = $this->type($clinic, 'Preferencial', 'P', 20);
        $emergency = $this->type($clinic, 'Emergencial', 'E', 30);

        app(SyncUnitTicketTypes::class)->handle($admin, $unit, [
            [
                'ticket_type_id' => $preferential->id,
                'active' => true,
                'display_name' => 'Atendimento Preferencial',
                'position' => 10,
            ],
            [
                'ticket_type_id' => $normal->id,
                'active' => true,
                'display_name' => 'Atendimento Normal',
                'position' => 20,
            ],
            [
                'ticket_type_id' => $emergency->id,
                'active' => false,
                'display_name' => null,
                'position' => 30,
            ],
        ]);

        $kiosk = Kiosk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        return [$kiosk, $clinic, $unit, $admin, [
            'normal' => $normal,
            'preferential' => $preferential,
            'emergency' => $emergency,
        ]];
    }

    private function type(Clinic $clinic, string $name, string $prefix, int $priority): TicketType
    {
        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'prefix' => $prefix,
            'priority' => $priority,
            'active' => true,
        ])->save();

        return $type->refresh();
    }
}
