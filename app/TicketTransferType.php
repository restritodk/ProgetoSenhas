<?php

namespace App;

enum TicketTransferType: string
{
    case QUEUE = 'queue';
    case DESK = 'desk';

    public function label(): string
    {
        return match ($this) {
            self::QUEUE => 'Fila geral',
            self::DESK => 'Mesa específica',
        };
    }
}
