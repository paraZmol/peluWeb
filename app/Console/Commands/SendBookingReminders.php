<?php

namespace App\Console\Commands;

use App\Actions\Booking\NotifyClient;
use App\Enums\BookingNotice;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:send-reminders')]
#[Description('Emails clients a reminder the day before their confirmed visit')]
class SendBookingReminders extends Command
{
    /** How long before the visit the reminder goes out. */
    public const int HOURS_BEFORE = 24;

    /** Booked with less notice than this, the confirmation is reminder enough. */
    public const int MIN_NOTICE_HOURS = 26;

    /** Too close to the visit, a "tomorrow" reminder would only confuse. */
    private const int LATEST_HOURS_BEFORE = 12;

    public function handle(NotifyClient $notify): int
    {
        $now = CarbonImmutable::now();
        $sent = 0;

        // A task for every salon: the scope is lifted for this query only.
        Booking::withoutGlobalScope('salon')
            ->where('status', BookingStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', $now->addHours(self::LATEST_HOURS_BEFORE))
            ->where('starts_at', '<=', $now->addHours(self::HOURS_BEFORE))
            ->chunkById(200, function ($bookings) use ($notify, $now, &$sent): void {
                foreach ($bookings as $booking) {
                    // Marked first: a mail sent twice is worse than one that fails once.
                    $claimed = Booking::withoutGlobalScope('salon')->whereKey($booking->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => $now]);

                    if ($claimed === 1 && $booking->created_at?->lessThanOrEqualTo($booking->starts_at->subHours(self::MIN_NOTICE_HOURS))) {
                        $notify->handle($booking, BookingNotice::Reminder);
                        $sent++;
                    }
                }
            });

        $this->info("Recordatorios enviados: {$sent}");

        return self::SUCCESS;
    }
}
