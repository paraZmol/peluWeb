<?php

namespace App\Enums;

/**
 * How money changed hands. The platform never verifies a payment: the team
 * records what they received.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Yape = 'yape';
    case Plin = 'plin';
    case Card = 'card';
    case Transfer = 'transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Efectivo',
            self::Yape => 'Yape',
            self::Plin => 'Plin',
            self::Card => 'Tarjeta',
            self::Transfer => 'Transferencia',
            self::Other => 'Otro',
        };
    }
}
