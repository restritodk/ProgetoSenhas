<?php

namespace App;

enum TicketSource: string
{
    case ADMIN = 'admin';
    case KIOSK = 'kiosk';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::KIOSK => 'Totem',
        };
    }
}
