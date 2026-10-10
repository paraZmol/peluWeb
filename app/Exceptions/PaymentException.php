<?php

namespace App\Exceptions;

use App\Support\Money;

/**
 * Business rule violations around money. Messages are user facing.
 */
class PaymentException extends BusinessRuleException
{
    public static function negativeAmount(): self
    {
        return new self('Los montos no pueden ser negativos.');
    }

    public static function discountTooLarge(): self
    {
        return new self('El descuento no puede dejar la cita por debajo de la seña que ya se pagó.');
    }

    public static function tooManyParts(int $max): self
    {
        return new self("Un cobro se puede repartir en {$max} métodos de pago como máximo.");
    }

    public static function doesNotAddUp(int $dueCents, int $receivedCents): self
    {
        return new self('Los pagos suman S/ '.Money::toAmount($receivedCents).' y hay que cobrar S/ '.Money::toAmount($dueCents).'.');
    }

    public static function nothingToRefund(): self
    {
        return new self('Esta reserva no tiene una seña por devolver.');
    }
}
