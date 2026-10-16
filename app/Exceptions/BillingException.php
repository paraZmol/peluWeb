<?php

namespace App\Exceptions;

/**
 * Business rule violations around plans and subscription payments.
 * Messages are user facing (the platform admin reads them).
 */
class BillingException extends BusinessRuleException
{
    public static function tooManyWithAgenda(int $withAgenda, string $plan, int $max): self
    {
        $extra = $withAgenda - $max;
        $people = $extra === 1 ? 'a 1 persona' : "a {$extra} personas";

        return new self("El salón tiene {$withAgenda} profesionales con agenda y el plan {$plan} incluye {$max}. Primero debe quitar la agenda {$people}.");
    }

    public static function invalidMonths(): self
    {
        return new self('Se paga por 1 mes o por un año (12 meses).');
    }

    public static function nothingPaid(): self
    {
        return new self('El monto recibido debe ser mayor que cero.');
    }

    public static function trialAlreadyExtended(): self
    {
        return new self('La prueba de este salón ya se prorrogó una vez.');
    }

    public static function alreadyPaying(): self
    {
        return new self('Este salón ya pagó: no está en prueba.');
    }
}
