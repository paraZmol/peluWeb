<?php

namespace App\Enums;

/**
 * Strict state machine of a booking:
 *
 *   pending ──> confirmed ──> in_service ──> completed
 *      │            ├──> cancelled
 *      │            └──> no_show
 *      ├──> cancelled
 *      └──> expired
 */
enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InService = 'in_service';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de seña',
            self::Confirmed => 'Confirmada',
            self::InService => 'En atención',
            self::Completed => 'Completada',
            self::Cancelled => 'Cancelada',
            self::NoShow => 'No vino',
            self::Expired => 'Vencida',
        };
    }

    /**
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled, self::Expired],
            self::Confirmed => [self::InService, self::Cancelled, self::NoShow],
            self::InService => [self::Completed],
            self::Completed, self::Cancelled, self::NoShow, self::Expired => [],
        };
    }

    public function canBecome(self $status): bool
    {
        return in_array($status, $this->next(), true);
    }

    /**
     * Statuses that hold the professional's time. A pending booking only
     * does so until its hold expires (see Booking::scopeHoldingSlot).
     *
     * @return list<self>
     */
    public static function holdingSlot(): array
    {
        return [self::Pending, self::Confirmed, self::InService];
    }

    public function isFinal(): bool
    {
        return $this->next() === [];
    }
}
