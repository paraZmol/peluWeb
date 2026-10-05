<?php

namespace App\Enums;

enum BusinessType: string
{
    case Barbershop = 'barbershop';
    case BeautySalon = 'beauty_salon';
    case Unisex = 'unisex';
    case Nails = 'nails';
    case Spa = 'spa';

    public function label(): string
    {
        return match ($this) {
            self::Barbershop => 'Barbería',
            self::BeautySalon => 'Salón de belleza',
            self::Unisex => 'Peluquería unisex',
            self::Nails => 'Centro de uñas',
            self::Spa => 'Estética y spa',
        };
    }
}
