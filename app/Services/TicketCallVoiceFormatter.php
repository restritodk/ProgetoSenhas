<?php

namespace App\Services;

use App\Models\TicketCall;

class TicketCallVoiceFormatter
{
    /**
     * @var array<string, string>
     */
    private const DIGIT_WORDS = [
        '0' => 'zero',
        '1' => 'um',
        '2' => 'dois',
        '3' => 'três',
        '4' => 'quatro',
        '5' => 'cinco',
        '6' => 'seis',
        '7' => 'sete',
        '8' => 'oito',
        '9' => 'nove',
    ];

    public function announce(TicketCall $call, bool $includeType = true, bool $includeDesk = true): string
    {
        if (
            ! $call->relationLoaded('ticket')
            || ($call->ticket !== null && ! $call->ticket->relationLoaded('ticketType'))
            || ! $call->relationLoaded('desk')
        ) {
            $call->loadMissing(['ticket.ticketType', 'desk']);
        }

        $typeName = mb_strtolower($call->ticket?->ticketType?->name ?? 'senha');
        $code = $call->ticket?->display_code ?? '';
        $deskName = $call->desk?->name ?? 'mesa';
        $spelledCode = $this->spellCode($code);

        if ($includeType && $includeDesk) {
            return sprintf(
                'Senha %s %s, dirigir-se à %s.',
                $typeName,
                $spelledCode,
                $this->spellDeskName($deskName),
            );
        }

        if ($includeType) {
            return sprintf('Senha %s %s.', $typeName, $spelledCode);
        }

        if ($includeDesk) {
            return sprintf(
                'Senha %s, dirigir-se à %s.',
                $spelledCode,
                $this->spellDeskName($deskName),
            );
        }

        return sprintf('Senha %s.', $spelledCode);
    }

    public function spellCode(string $code): string
    {
        $code = trim($code);

        if ($code === '') {
            return '';
        }

        $parts = [];

        foreach (mb_str_split($code) as $char) {
            if (ctype_alpha($char)) {
                $parts[] = mb_strtoupper($char);

                continue;
            }

            if (ctype_digit($char)) {
                $parts[] = self::DIGIT_WORDS[$char] ?? $char;

                continue;
            }

            if ($char === '-' || $char === '_') {
                continue;
            }
        }

        return implode(' ', $parts);
    }

    public function spellDeskName(string $deskName): string
    {
        $deskName = trim($deskName);

        if ($deskName === '') {
            return 'mesa';
        }

        return preg_replace_callback(
            '/\d+/u',
            function (array $matches): string {
                $digits = ltrim($matches[0], '0');

                if ($digits === '') {
                    $digits = '0';
                }

                $words = [];

                foreach (str_split($digits) as $digit) {
                    $words[] = self::DIGIT_WORDS[$digit] ?? $digit;
                }

                return implode(' ', $words);
            },
            $deskName,
        ) ?? $deskName;
    }
}
