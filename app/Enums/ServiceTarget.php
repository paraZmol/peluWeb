<?php

namespace App\Enums;

enum ServiceTarget: string
{
    case Men = 'men';
    case Women = 'women';
    case Unisex = 'unisex';
    case Kids = 'kids';

    public function label(): string
    {
        return match ($this) {
            self::Men => 'Hombre',
            self::Women => 'Mujer',
            self::Unisex => 'Unisex',
            self::Kids => 'Niños',
        };
    }
}
