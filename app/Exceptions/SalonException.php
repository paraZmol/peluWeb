<?php

namespace App\Exceptions;

/**
 * Business rule violations in salon settings. Messages are user facing.
 */
class SalonException extends BusinessRuleException
{
    public static function galleryFull(int $max): self
    {
        return new self("Tu galería ya tiene {$max} fotos. Elimina alguna para subir otra.");
    }

    public static function tooManyClientPhotos(int $max): self
    {
        return new self("Una ficha guarda {$max} fotos como máximo. Elimina alguna para subir otra.");
    }
}
