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

    public static function tooManyCategories(int $max): self
    {
        return new self("Puedes tener hasta {$max} categorías de gastos.");
    }

    public static function cashAlreadyOpen(): self
    {
        return new self('La caja ya está abierta. Ciérrala antes de abrir otra.');
    }

    public static function cashAlreadyClosed(): self
    {
        return new self('Esta caja ya se cerró.');
    }

    public static function nothingToSettle(): self
    {
        return new self('No hay comisiones pendientes en ese período.');
    }

    public static function settlementChanged(): self
    {
        return new self('Lo pendiente cambió mientras tenías la pantalla abierta. Revisa el nuevo total y vuelve a liquidar.');
    }

    public static function expenseInClosedCash(): self
    {
        return new self('Este gasto entró en una caja que ya se cerró y no se puede cambiar ni eliminar. Si hubo un error, anótalo y regístralo aparte.');
    }

    public static function expenseIsSettlement(): self
    {
        return new self('Este gasto es el pago de una liquidación de comisiones y no se puede cambiar desde aquí.');
    }

    public static function nothingToRefund(): self
    {
        return new self('Esta reserva no tiene una seña por devolver.');
    }
}
