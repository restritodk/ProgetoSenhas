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

    /**
     * Nouns that take "ao" (masculine). Anything else keeps "à", which matches Mesa.
     *
     * @var list<string>
     */
    private const MASCULINE_DESTINATIONS = [
        'guiche',
        'balcao',
        'consultorio',
        'box',
        'posto',
        'modulo',
        'laboratorio',
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
        $spelledCode = $this->spellCode($call->ticket?->display_code ?? '');
        $lead = $includeType
            ? trim('Senha '.$typeName.' '.$spelledCode)
            : trim('Senha '.$spelledCode);
        $sentence = $lead.'.';

        if (! $includeDesk) {
            return $sentence;
        }

        return $sentence.' '.$this->destinationInstruction($call->desk?->name ?? 'mesa');
    }

    public function destinationInstruction(string $deskName): string
    {
        return sprintf(
            'Dirigir-se %s %s.',
            $this->destinationPreposition($deskName),
            $this->spokenDestination($deskName),
        );
    }

    public function destinationPreposition(string $deskName): string
    {
        $noun = $this->normalizeNoun($this->destinationNoun($deskName));

        if (in_array($noun, self::MASCULINE_DESTINATIONS, true)) {
            return 'ao';
        }

        return 'à';
    }

    public function spokenDestination(string $deskName): string
    {
        $spoken = mb_strtolower(trim($this->spellDeskName($deskName)));

        if ($spoken === '') {
            return 'mesa';
        }

        if (preg_match('/\p{L}/u', $spoken) !== 1) {
            return 'mesa '.$spoken;
        }

        return $spoken;
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

    public function destinationNoun(string $deskName): string
    {
        $deskName = trim($deskName);

        if (preg_match('/^\p{L}+/u', $deskName, $matches) === 1) {
            return $matches[0];
        }

        return 'mesa';
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

    private function normalizeNoun(string $word): string
    {
        $word = mb_strtolower(trim($word));
        $word = strtr($word, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);

        return preg_replace('/[^a-z]/', '', $word) ?? '';
    }
}
