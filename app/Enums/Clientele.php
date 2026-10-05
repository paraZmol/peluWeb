<?php

namespace App\Enums;

enum Clientele: string
{
    case Men = 'men';
    case Women = 'women';
    case Everyone = 'everyone';

    public function label(): string
    {
        return match ($this) {
            self::Men => 'Hombres',
            self::Women => 'Mujeres',
            self::Everyone => 'Todo público',
        };
    }
}
