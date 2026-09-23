<?php

namespace Tests\Unit;

use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Services\TicketCallVoiceFormatter;
use App\TicketCallType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketCallVoiceFormatterTest extends TestCase
{
    #[DataProvider('codeExamples')]
    public function test_spell_code_examples(string $code, string $expected): void
    {
        $formatter = new TicketCallVoiceFormatter;

        $this->assertSame($expected, $formatter->spellCode($code));
    }

    #[DataProvider('deskExamples')]
    public function test_spell_desk_name_examples(string $desk, string $expected): void
    {
        $formatter = new TicketCallVoiceFormatter;

        $this->assertSame($expected, $formatter->spellDeskName($desk));
    }

    public function test_announce_uses_ticket_type_name_without_hardcoding_npe(): void
    {
        $formatter = new TicketCallVoiceFormatter;

        $type = new TicketType;
        $type->forceFill(['name' => 'Gestante', 'prefix' => 'G']);

        $ticket = new Ticket;
        $ticket->forceFill(['sequence_number' => 7]);
        $ticket->setRelation('ticketType', $type);

        $desk = new Desk;
        $desk->forceFill(['name' => 'Mesa 04']);

        $call = new TicketCall;
        $call->forceFill(['call_type' => TicketCallType::INITIAL]);
        $call->setRelation('ticket', $ticket);
        $call->setRelation('desk', $desk);

        $announcement = $formatter->announce($call);

        $this->assertSame(
            'Senha gestante G zero zero sete, dirigir-se à Mesa quatro.',
            $announcement,
        );
        $this->assertStringNotContainsString('preferencial', $announcement);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function codeExamples(): array
    {
        return [
            'P023' => ['P023', 'P zero dois três'],
            'N001' => ['N001', 'N zero zero um'],
            'E012' => ['E012', 'E zero um dois'],
            'G007' => ['G007', 'G zero zero sete'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function deskExamples(): array
    {
        return [
            'Mesa 04' => ['Mesa 04', 'Mesa quatro'],
            'Guichê 12' => ['Guichê 12', 'Guichê um dois'],
        ];
    }
}
