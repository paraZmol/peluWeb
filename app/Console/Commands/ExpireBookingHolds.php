<?php

namespace App\Console\Commands;

use App\Actions\Booking\ChangeBookingStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\CurrentSalon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:expire-holds')]
#[Description('Marks as expired the pending bookings whose deposit was not confirmed in time')]
class ExpireBookingHolds extends Command
{
    /**
     * Housekeeping only: an expired hold already stopped blocking its slot
     * (Booking::scopeHoldingSlot), whether or not this ran.
     */
    public function handle(ChangeBookingStatus $status, CurrentSalon $currentSalon): int
    {
        // A console task for every salon: the deliberate, greppable bypass.
        $currentSalon->bypass();

        $expired = 0;

        Booking::query()
            ->where('status', BookingStatus::Pending)
            ->where('hold_expires_at', '<=', now())
            ->chunkById(200, function ($bookings) use ($status, &$expired): void {
                foreach ($bookings as $booking) {
                    $expired += $status->expire($booking) === null ? 0 : 1;
                }
            });

        $this->info("Reservas vencidas: {$expired}");

        return self::SUCCESS;
    }
}
