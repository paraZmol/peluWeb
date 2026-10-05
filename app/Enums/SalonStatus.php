<?php

namespace App\Enums;

enum SalonStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'En prueba',
            self::Active => 'Activo',
            self::PastDue => 'Pago vencido',
            self::Suspended => 'Suspendido',
        };
    }
}
