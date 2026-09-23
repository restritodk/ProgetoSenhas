<?php

namespace App;

enum QueueCriticalMode: string
{
    case AlwaysFirst = 'always_first';
    case Weighted = 'weighted';

    public function label(): string
    {
        return match ($this) {
            self::AlwaysFirst => 'Sempre chamar antes das demais',
            self::Weighted => 'Dar alta prioridade, mas seguir regras de distribuição',
        };
    }
}
