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

    public static function changedMeanwhile(): self
    {
        return new self('Alguien acaba de cambiar esta reserva. Vuelve a intentarlo.');
    }

    public static function staffCannotTake(): self
    {
        return new self('Esa persona no puede atender esta cita: no está activa o no hace todos sus servicios.');
    }

    public static function tooLateToMove(int $hours): self
    {
        return new self("Solo puedes cambiar la hora con al menos {$hours} h de anticipación. Escribe al salón para coordinar.");
    }

    public static function tooManyMoves(): self
    {
        return new self('Ya cambiaste esta reserva varias veces. Escribe al salón para coordinar otro cambio.');
    }

    public static function promotionGone(): self
    {
        return new self('La promoción ya no está disponible para ese horario. Elige el horario de nuevo: verás el precio actualizado antes de confirmar.');
    }

    public static function promotionPlaceTaken(string $name): self
    {
        return new self("El plazo de la seña venció y el cupo de «{$name}» ya lo tomó otra reserva: no se puede confirmar a ese precio. Avisa a la persona: si acepta el precio normal, cancela esta reserva y crea una nueva; si no, devuélvele la seña completa.");
    }

    public static function promotionNotValidThen(string $name): self
    {
        return new self("La promoción «{$name}» de tu reserva no vale en ese horario. Elige otro, o pide al salón que la mueva.");
    }

    public static function alreadyStarted(): self
    {
        return new self('La hora de esta cita ya pasó. Escribe al salón para cualquier cambio.');
    }
}
