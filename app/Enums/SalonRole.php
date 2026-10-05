<?php

namespace App\Enums;

enum SalonRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Dueño',
            self::Staff => 'Personal',
        };
    }
}
