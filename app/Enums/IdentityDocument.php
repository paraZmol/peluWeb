<?php

namespace App\Enums;

/**
 * Identity documents a complaint sheet accepts.
 */
enum IdentityDocument: string
{
    case Dni = 'dni';
    case ForeignerCard = 'ce';

    public function label(): string
    {
        return match ($this) {
            self::Dni => 'DNI',
            self::ForeignerCard => 'Carné de extranjería',
        };
    }
}
