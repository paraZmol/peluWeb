<?php

namespace App\Enums;

/**
 * What a complaint sheet is about.
 */
enum ComplaintItemType: string
{
    case Service = 'service';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::Service => 'Servicio',
            self::Product => 'Producto',
        };
    }
}
