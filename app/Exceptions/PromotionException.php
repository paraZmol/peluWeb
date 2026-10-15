<?php

namespace App\Exceptions;

/**
 * Business rule violations around promotions. Messages are user facing.
 */
class PromotionException extends BusinessRuleException
{
    public static function tooManyActive(int $max): self
    {
        return new self("Puedes tener hasta {$max} promociones publicadas a la vez. Pausa o deja terminar una antes de publicar otra.");
    }

    public static function lockedByBookings(): self
    {
        return new self('Esta promoción ya tiene reservas: solo puedes pausarla, alargar la fecha, subir los cupos o cambiar el título. Para lo demás, duplícala.');
    }

    public static function needsReview(): self
    {
        return new self('Un servicio de esta promoción ya no se ofrece. Duplícala y elige los servicios de nuevo.');
    }

    public static function alreadyEnded(): self
    {
        return new self('Esta promoción ya terminó. Duplícala para lanzarla otra vez.');
    }

    public static function onlyDraftsAreDeleted(): self
    {
        return new self('Solo se eliminan los borradores. Una promoción publicada se pausa.');
    }
}
