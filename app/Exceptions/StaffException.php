<?php

namespace App\Exceptions;

/**
 * Business rule violations around professionals and their hours.
 * Messages are user facing.
 */
class StaffException extends BusinessRuleException
{
    public static function limitReached(int $max): self
    {
        return new self("Tu salón ya tiene {$max} profesionales. Quita a alguien para agregar otra persona.");
    }

    public static function accountNotInTeam(): self
    {
        return new self('Esa cuenta no forma parte de tu equipo.');
    }

    public static function accountAlreadyLinked(): self
    {
        return new self('Esa cuenta ya está vinculada a otro profesional.');
    }

    public static function overlappingRanges(): self
    {
        return new self('Hay horarios que se cruzan el mismo día. Revísalos y vuelve a guardar.');
    }

    public static function tooManyTimeOff(int $max): self
    {
        return new self("Ya hay {$max} ausencias programadas. Elimina las que no necesites.");
    }

    public static function bookingsAffected(int $count, string $listed): self
    {
        $more = $count > substr_count($listed, '(') ? ' y más' : '';
        $noun = $count === 1 ? 'una cita que quedaría' : "{$count} citas que quedarían";

        return new self("Hay {$noun} sin atender: {$listed}{$more}. Cancélalas o muévelas desde la Agenda y vuelve a intentar.");
    }

    public static function invalidPeriod(): self
    {
        return new self('Esa ausencia termina antes de empezar. Revisa las horas.');
    }

    public static function holidayLimitReached(int $max): self
    {
        return new self("Ya tienes {$max} días de cierre programados. Elimina los que ya pasaron.");
    }
}
