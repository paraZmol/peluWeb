<?php

namespace App\Enums;

/**
 * What a salon does with its clients' reviews.
 */
enum ReviewMode: string
{
    /** Not asked for, not shown. */
    case Hidden = 'hidden';
    /** Asked for; only the salon reads them. */
    case Private = 'private';
    /** All of them on the salon's page, with the average. */
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Hidden => 'Ocultas',
            self::Private => 'Privadas',
            self::Public => 'Públicas',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Hidden => 'No se piden ni se muestran.',
            self::Private => 'Se piden y solo las lee tu equipo, como retroalimentación.',
            self::Public => 'Se muestran en tu página todas las que recibas en este modo, con el promedio. No se pueden ocultar las negativas.',
        };
    }

    public function collects(): bool
    {
        return $this !== self::Hidden;
    }
}
