<?php

namespace App\Enums;

enum ClientPhotoKind: string
{
    case Before = 'before';
    case After = 'after';

    public function label(): string
    {
        return match ($this) {
            self::Before => 'Antes',
            self::After => 'Después',
        };
    }
}
