<?php

namespace App\Actions\Booking;

use App\Enums\BookingNotice;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\PushSubscription;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Staff;
use App\Notifications\StaffBookingNotification;

/**
 * Tells whoever attends a booking that their agenda changed, on the
 * devices they allowed. Not when they made the change themselves, and not
 * for what is only the client's business (reminder, review request).
 */
class NotifyStaff
{
    /** "mié 7 oct, 10:00 a. m.": short, it goes in a notification. */
    private const string WHEN = 'ddd D MMM, h:mm a';

    public function handle(Booking $booking, BookingNotice $notice): void
    {
        if ($notice->isOptional()) {
            return;
        }

        $user = Staff::withoutGlobalScope('salon')->where('salon_id', $booking->salon_id)->with('user')->find($booking->staff_id)?->user;

        if ($user === null || $user->id === auth()->id()) {
            return;
        }

        // Nothing to send to: no job is queued for it.
        if (! PushSubscription::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        $salon = Salon::withTrashed()->findOrFail($booking->salon_id);

        $user->notify(new StaffBookingNotification($notice, [
            'salon' => $salon->name,
            'when' => $booking->starts_at->setTimezone($salon->timezone)->isoFormat(self::WHEN),
            'services' => BookingService::query()->where('booking_id', $booking->id)->orderBy('position')->pluck('name')->implode(', '),
            'client' => (string) SalonClient::withoutGlobalScope('salon')->where('salon_id', $booking->salon_id)->whereKey($booking->salon_client_id)->value('name'),
            'url' => route('agenda.index', ['fecha' => $booking->starts_at->setTimezone($salon->timezone)->toDateString()]),
            'code' => $booking->code,
        ]));
    }
}
