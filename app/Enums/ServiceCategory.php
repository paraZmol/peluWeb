<?php

namespace App\Enums;

enum ServiceCategory: string
{
    case MenCut = 'men_cut';
    case Beard = 'beard';
    case WomenCut = 'women_cut';
    case Kids = 'kids';
    case Color = 'color';
    case Treatment = 'treatment';
    case Styling = 'styling';
    case Nails = 'nails';
    case BrowsLashes = 'brows_lashes';
    case Waxing = 'waxing';
    case Aesthetics = 'aesthetics';

    public function label(): string
    {
        return match ($this) {
            self::MenCut => 'Cortes de hombre',
            self::Beard => 'Barba y afeitado',
            self::WomenCut => 'Cortes de mujer',
            self::Kids => 'Infantil',
            self::Color => 'Color',
            self::Treatment => 'Tratamientos y químicos',
            self::Styling => 'Peinados y eventos',
            self::Nails => 'Uñas',
            self::BrowsLashes => 'Cejas y pestañas',
            self::Waxing => 'Depilación',
            self::Aesthetics => 'Estética',
        };
    }
}
