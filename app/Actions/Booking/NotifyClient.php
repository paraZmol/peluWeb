<?php

namespace App\Actions\Booking;

use App\Enums\BookingNotice;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Review;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\Staff;
use App\Notifications\BookingNotification;
use App\Support\Money;

/**
 * Writes to the client about their booking, by email. Only people with an
 * account and a verified address get it: whoever the salon wrote down by
 * name and phone is still reached by WhatsApp, with a tap.
 *
 * Reads everything now and hands the notification plain text: by the time
 * the queue sends it there is no current salon to load the booking with.
 */
class NotifyClient
{
    /** "miércoles 7 de octubre de 2026, 10:00 a. m.", in the app's locale (Spanish). */
    private const string WHEN = 'dddd D [de] MMMM [de] YYYY, h:mm a';

    public function __construct(private readonly NotifyStaff $staff) {}

    public function handle(Booking $booking, BookingNotice $notice): void
    {
        // Whoever attends hears of it too, whether or not the client has an account.
        $this->staff->handle($booking, $notice);

        $user = SalonClient::withoutGlobalScope('salon')->with('user')->find($booking->salon_client_id)?->user;

        if ($user === null || ! $user->hasVerifiedEmail()) {
            return;
        }

        // What happened to the booking is always told; the rest is a courtesy.
        if ($notice->isOptional() && ! $user->booking_reminders) {
            return;
        }

        $salon = Salon::withTrashed()->findOrFail($booking->salon_id);

        if ($notice === BookingNotice::ReviewRequest
            && (! $salon->review_mode->collects() || $booking->starts_at->lessThan(now()->subDays(Review::WINDOW_DAYS)))) {
            return;
        }

        $start = $booking->starts_at->setTimezone($salon->timezone);

        $user->notify(new BookingNotification($notice, [
            'salon' => $salon->name,
            'when' => ucfirst($start->isoFormat(self::WHEN)),
            'services' => BookingService::query()->where('booking_id', $booking->id)->orderBy('position')->get()
                ->map(fn (BookingService $service): string => $service->displayName())->implode(', '),
            'staff' => (string) Staff::withoutGlobalScope('salon')->withTrashed()->whereKey($booking->staff_id)->value('name'),
            'address' => $this->address($salon),
            'url' => route('bookings.show', $booking->code),
        ], $this->notes($booking, $notice, $salon)));
    }

    private function address(Salon $salon): ?string
    {
        $parts = array_filter([$salon->address, $salon->district, $salon->city]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * What this occasion adds about the deposit.
     *
     * @return list<string>
     */
    private function notes(Booking $booking, BookingNotice $notice, Salon $salon): array
    {
        // What the promotion took off: said in every mail about the booking.
        $promotion = $booking->promotion_cents === 0 ? []
            : ["Promoción {$booking->promotion_name}: −S/ ".Money::toAmount($booking->promotion_cents).'. Total: S/ '.Money::toAmount($booking->price_cents).'.'];

        if ($booking->deposit_cents === 0) {
            return $promotion;
        }

        $deposit = 'S/ '.Money::toAmount($booking->deposit_cents);
        $isPaid = $booking->deposit_paid_at !== null;

        return [...$promotion, ...match (true) {
            $notice === BookingNotice::Received && $booking->hold_expires_at !== null => [
                "Seña: {$deposit}. Envíala antes de las ".$booking->hold_expires_at->setTimezone($salon->timezone)->isoFormat('h:mm a').' para conservar el horario'.($booking->promotion_cents > 0 ? ' y el precio de la promoción' : '').'.',
            ],
            $notice === BookingNotice::Confirmed && $isPaid => ["Seña recibida: {$deposit}."],
            $notice === BookingNotice::Cancelled && $isPaid && $booking->deposit_refundable === true => ["El salón te devolverá la seña de {$deposit}."],
            $notice === BookingNotice::Cancelled && $isPaid => ["La seña de {$deposit} no se devuelve: la cancelación llegó fuera del plazo del salón."],
            default => [],
        }];
    }
}
