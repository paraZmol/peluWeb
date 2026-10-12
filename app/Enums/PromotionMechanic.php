<?php

namespace App\Enums;

/**
 * What a promotion does to the price of a booking. The cards the owner
 * picks from (discount, gift, quiet hours, season) all end in one of these.
 */
enum PromotionMechanic: string
{
    /** A percentage or an amount off the included services. */
    case Discount = 'discount';

    /** One service for free when booked together with an included one. */
    case Gift = 'gift';

    public function label(): string
    {
        return match ($this) {
            self::Discount => 'Descuento',
            self::Gift => 'Servicio de regalo',
        };
    }
}
