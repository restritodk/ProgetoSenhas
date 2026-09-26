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
            'Senha gestante G zero zero sete. Dirigir-se à mesa quatro.',
            $announcement,
        );
        $this->assertStringNotContainsString('preferencial', $announcement);
    }

    public function test_n001_keeps_leading_zeros_in_the_phrase(): void
    {
        $announcement = $this->announceCode('N', 1, 'Normal', 'Mesa 04');

        $this->assertSame(
            'Senha N zero zero um. Dirigir-se à mesa quatro.',
            $announcement,
        );
    }

    public function test_p017_spells_the_real_code_and_mesa(): void
    {
        $announcement = $this->announceCode('P', 17, 'Preferencial', 'Mesa 04', includeType: false);

        $this->assertSame(
            'Senha P zero um sete. Dirigir-se à mesa quatro.',
            $announcement,
        );
        $this->assertStringNotContainsString('P001', $announcement);
        $this->assertStringNotContainsString('Mesa 02', $announcement);
    }

    public function test_e105_spells_zero_between_digits(): void
    {
        $announcement = $this->announceCode('E', 105, 'Emergencial', 'Mesa 01', includeType: false);

        $this->assertSame(
            'Senha E um zero cinco. Dirigir-se à mesa um.',
            $announcement,
        );
    }

    public function test_guiche_uses_masculine_preposition_and_real_number(): void
    {
        $announcement = $this->announceCode('N', 25, 'Normal', 'Guichê 02', includeType: false);

        $this->assertSame(
            'Senha N zero dois cinco. Dirigir-se ao guichê dois.',
            $announcement,
        );
    }

    public function test_each_call_uses_only_its_own_desk(): void
    {
        $mesa = $this->announceCode('P', 17, 'Preferencial', 'Mesa 04', includeType: false);
        $guiche = $this->announceCode('N', 25, 'Normal', 'Guichê 02', includeType: false);

        $this->assertStringContainsString('mesa quatro', $mesa);
        $this->assertStringNotContainsString('guichê', $mesa);
        $this->assertStringNotContainsString('N zero dois cinco', $mesa);

        $this->assertStringContainsString('guichê dois', $guiche);
        $this->assertStringNotContainsString('mesa quatro', $guiche);
        $this->assertStringNotContainsString('P zero um sete', $guiche);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function codeExamples(): array
    {
        return [
            'P023' => ['P023', 'P zero dois três'],
            'N001' => ['N001', 'N zero zero um'],
            'P017' => ['P017', 'P zero um sete'],
            'E105' => ['E105', 'E um zero cinco'],
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
            'Guichê 02' => ['Guichê 02', 'Guichê dois'],
        ];
    }

    private function announceCode(
        string $prefix,
        int $sequence,
        string $typeName,
        string $deskName,
        bool $includeType = false,
    ): string {
        $type = new TicketType;
        $type->forceFill(['name' => $typeName, 'prefix' => $prefix]);

        $ticket = new Ticket;
        $ticket->forceFill(['sequence_number' => $sequence]);
        $ticket->setRelation('ticketType', $type);

        $desk = new Desk;
        $desk->forceFill(['name' => $deskName]);

        $call = new TicketCall;
        $call->forceFill(['call_type' => TicketCallType::INITIAL]);
        $call->setRelation('ticket', $ticket);
        $call->setRelation('desk', $desk);

        return (new TicketCallVoiceFormatter)->announce($call, $includeType, true);
    }
}
