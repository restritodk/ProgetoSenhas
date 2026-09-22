<?php

namespace App;

enum TicketCallType: string
{
    case INITIAL = 'initial';
    case RECALL = 'recall';

    public function label(): string
    {
        return match ($this) {
            self::INITIAL => 'Chamada',
            self::RECALL => 'Rechamada',
        };
    }
}
