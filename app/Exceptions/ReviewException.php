<?php

namespace App\Exceptions;

/**
 * Business rule violations around reviews. Messages are user facing.
 */
class ReviewException extends BusinessRuleException
{
    public static function visitNotCompleted(): self
    {
        return new self('Solo se puede opinar de una cita que ya se atendió.');
    }

    public static function notCollected(): self
    {
        return new self('Este salón no recibe reseñas por ahora.');
    }

    public static function tooLate(int $days): self
    {
        return new self("El plazo para opinar de esta cita ya pasó ({$days} días).");
    }

    public static function alreadyReviewed(): self
    {
        return new self('Ya dejaste tu reseña de esta cita.');
    }

    public static function alreadyResolved(): self
    {
        return new self('El equipo de peluWeb ya revisó esta reseña.');
    }

    public static function alreadyReported(): self
    {
        return new self('Esta reseña ya está en revisión.');
    }
}
