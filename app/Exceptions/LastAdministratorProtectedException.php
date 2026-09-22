<?php

namespace App\Exceptions;

use RuntimeException;

class LastAdministratorProtectedException extends RuntimeException
{
    public static function cannotDeactivate(): self
    {
        return new self('Não é possível desativar o último administrador ativo da clínica.');
    }

    public static function cannotDemote(): self
    {
        return new self('Não é possível alterar o perfil do último administrador ativo da clínica.');
    }
}
