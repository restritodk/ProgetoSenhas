<?php

namespace App;

enum TicketStatus: string
{
    case WAITING = 'waiting';
    case CALLED = 'called';
    case IN_SERVICE = 'in_service';
    case COMPLETED = 'completed';
    case NO_SHOW = 'no_show';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::WAITING => 'Aguardando',
            self::CALLED => 'Chamada',
            self::IN_SERVICE => 'Em atendimento',
            self::COMPLETED => 'Concluída',
            self::NO_SHOW => 'Não compareceu',
            self::CANCELLED => 'Cancelada',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::WAITING => [self::CALLED, self::CANCELLED],
            self::CALLED => [self::IN_SERVICE, self::NO_SHOW, self::WAITING, self::CANCELLED],
            self::IN_SERVICE => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED, self::NO_SHOW, self::CANCELLED => [],
        };
    }

    public function isSelectableInQueue(): bool
    {
        return $this === self::WAITING;
    }
}
