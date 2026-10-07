<?php

namespace App\Exceptions;

/**
 * Business rule violations around bookings. Messages are user facing.
 */
class BookingException extends BusinessRuleException
{
    public static function slotTaken(): self
    {
        return new self('Ese horario se acaba de ocupar. Elige otro, por favor.');
    }

    public static function serviceUnavailable(): self
    {
        return new self('Uno de los servicios ya no está disponible. Vuelve a elegir.');
    }

    public static function onlyAddons(): self
    {
        return new self('Los adicionales se reservan junto con otro servicio.');
    }

    public static function tooManyServices(int $max): self
    {
        return new self("Puedes reservar hasta {$max} servicios a la vez.");
    }

    public static function nobodyPerformsThem(): self
    {
        return new self('Nadie del equipo hace todos esos servicios juntos. Resérvalos por separado.');
    }

    public static function bookingsPaused(): self
    {
        return new self('Este salón no está recibiendo reservas en línea por ahora.');
    }

    public static function tooManyUpcoming(int $max): self
    {
        return new self("Ya tienes {$max} reservas próximas en este salón. Cancela alguna para reservar otra.");
    }

    public static function tooManyToday(): self
    {
        return new self('Hiciste muchas reservas hoy en este salón. Escríbeles por WhatsApp para coordinar.');
    }

    public static function cannotChange(string $currentStatus): self
    {
        return new self("Esta reserva está \"{$currentStatus}\" y ya no admite ese cambio.");
    }

    public static function notStartedYet(): self
    {
        return new self('Solo puedes marcar que no vino cuando ya pasó la hora de la cita.');
    }

    public static function notToday(): self
    {
        return new self('Solo puedes marcar que llegó el mismo día de la cita.');
    }

    public static function alreadyStarted(): self
    {
        return new self('La hora de esta cita ya pasó. Escribe al salón para cualquier cambio.');
    }
}
