<?php

namespace App;

enum UserRole: string
{
    case ADMINISTRATOR = 'administrator';
    case SUPERVISOR = 'supervisor';
    case ATTENDANT = 'attendant';

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administrador',
            self::SUPERVISOR => 'Supervisor',
            self::ATTENDANT => 'Atendente',
        };
    }
}
