<?php

namespace App\Exceptions;

/**
 * Business rule violations around the Libro de Reclamaciones. Messages are
 * user facing.
 */
class ComplaintException extends BusinessRuleException
{
    public static function immutable(): self
    {
        return new self('Una hoja del Libro de Reclamaciones no se puede cambiar ni borrar.');
    }

    public static function alreadyAnswered(): self
    {
        return new self('Esta hoja ya tiene respuesta: se registra una sola vez.');
    }
}
