<?php

namespace App\Enums;

/**
 * Where someone stands on a salon's waiting list for a day.
 */
enum WaitlistStatus: string
{
    case Waiting = 'waiting';
    /** Told that a time opened up; theirs to take for a while. */
    case Offered = 'offered';
    case Booked = 'booked';
    /** The day went by. */
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'En lista de espera',
            self::Offered => 'Hay un horario libre',
            self::Booked => 'Reservaste',
            self::Expired => 'El día ya pasó',
            self::Cancelled => 'Saliste de la lista',
        };
    }

    /** Still in line, or with a turn in hand. */
    public function isActive(): bool
    {
        return $this === self::Waiting || $this === self::Offered;
    }
}
