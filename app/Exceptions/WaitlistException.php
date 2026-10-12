<?php

namespace App\Exceptions;

/**
 * Business rule violations around the waiting list. Messages are user facing.
 */
class WaitlistException extends BusinessRuleException
{
    public static function thereAreTimes(): self
    {
        return new self('Ese día sí tiene horarios libres: puedes reservar ahora.');
    }

    public static function alreadyWaiting(): self
    {
        return new self('Ya estás en la lista de espera de ese día.');
    }

    public static function tooMany(int $max): self
    {
        return new self("Puedes estar en la lista de espera de hasta {$max} días a la vez en este salón.");
    }

    public static function lineIsFull(int $max): self
    {
        return new self("La fila ya tiene {$max} personas. Cierra a quienes ya se atendieron o se fueron.");
    }

    public static function placeIsClosed(): self
    {
        return new self('Esa persona ya salió de la fila.');
    }

    public static function dayOutOfRange(): self
    {
        return new self('Ese día ya pasó o todavía no se puede reservar.');
    }
}
