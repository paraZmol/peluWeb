<?php

namespace App\Enums;

/**
 * Where a salon stands with its subscription, worked out from its dates
 * (see App\Domain\Billing\SubscriptionStanding). Only Suspended is set by
 * hand; the rest follows from the trial and what is paid.
 */
enum SubscriptionState: string
{
    case Trial = 'trial';
    case Active = 'active';
    /** Past its last covered day, still working for a few days. */
    case Grace = 'grace';
    /** Everything can be seen and exported; nothing changes until it pays. */
    case ReadOnly = 'read_only';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'En prueba',
            self::Active => 'Al día',
            self::Grace => 'Pago vencido',
            self::ReadOnly => 'En pausa',
            self::Suspended => 'Suspendido',
        };
    }

    public function isWritable(): bool
    {
        return $this !== self::ReadOnly && $this !== self::Suspended;
    }
}
