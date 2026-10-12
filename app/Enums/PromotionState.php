<?php

namespace App\Enums;

/**
 * Where a promotion stands today, as the owner sees it: what they did
 * with it (PromotionStatus) read against its dates and its services.
 */
enum PromotionState: string
{
    case Draft = 'draft';
    /** Published, and its first day is still to come. */
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';
    /** A service it names is no longer offered: it stopped applying. */
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Scheduled => 'Programada',
            self::Active => 'Activa',
            self::Paused => 'Pausada',
            self::Ended => 'Terminada',
            self::NeedsReview => 'Revisar',
        };
    }
}
