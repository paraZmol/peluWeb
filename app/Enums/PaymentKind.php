<?php

namespace App\Enums;

/**
 * Why money moved. Amounts are stored positive; the kind gives the sign.
 */
enum PaymentKind: string
{
    case Deposit = 'deposit';
    case Charge = 'charge';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Seña',
            self::Charge => 'Cobro de la cita',
            self::Refund => 'Seña devuelta',
        };
    }

    public function isOutgoing(): bool
    {
        return $this === self::Refund;
    }
}
