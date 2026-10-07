<?php

namespace App\Enums;

enum TimeOffReason: string
{
    case Vacation = 'vacation';
    case Leave = 'leave';
    case Block = 'block';

    public function label(): string
    {
        return match ($this) {
            self::Vacation => 'Vacaciones',
            self::Leave => 'Permiso',
            self::Block => 'Bloqueo',
        };
    }
}
