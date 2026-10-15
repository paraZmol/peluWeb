<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Ven con alguien" when only one of them came: the salon says who came,
 * the other line goes, and the one who came pays their own service at its
 * normal price — the promotion was for two (docs/promociones.md). A paid
 * deposit still counts towards it (the balance is price less deposit); it
 * is not a cancellation nor a no-show. The time stays booked.
 */
class DropCompanion
{
    /**
     * @param  bool  $companionCame  true when who came is the one who was coming along
     */
    public function handle(Booking $booking, bool $companionCame, ?User $by): Booking
    {
        return DB::transaction(function () use ($booking, $companionCame, $by): Booking {
            $locked = Booking::withoutGlobalScope('salon')->lockForUpdate()->findOrFail($booking->id);
            $lines = BookingService::query()->where('booking_id', $locked->id)->get();
            $companion = $lines->firstWhere('companion_name', '!==', null);

            if ($companion === null || $lines->count() !== 2 || ! in_array($locked->status, [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::InService], true)) {
                throw BookingException::noCompanionToDrop();
            }

            $kept = $companionCame ? $companion : $lines->firstWhere('id', '!==', $companion->id);
            $priceCents = $kept->list_price_cents ?? $kept->price_cents;

            // The deposit was a percent of the total for two: once paid it
            // may exceed one normal price, and the visit could never be
            // settled (the balance would be negative).
            if ($locked->depositPaidCents() > $priceCents) {
                throw BookingException::depositAboveWhatIsLeft();
            }

            $lines->firstWhere('id', '!==', $kept?->id)?->delete();
            $kept?->forceFill(['price_cents' => $priceCents, 'position' => 0])->save();

            $locked->forceFill([
                // Not paid yet: the same share of what is left.
                'deposit_cents' => $locked->deposit_paid_at === null && $locked->price_cents > 0
                    ? intdiv($locked->deposit_cents * $priceCents + intdiv($locked->price_cents, 2), $locked->price_cents)
                    : $locked->deposit_cents,
                'price_cents' => $priceCents,
                'promotion_id' => null,
                'promotion_name' => null,
                'promotion_cents' => 0,
            ])->save();

            // Without the name: it must not outlive the line it was on.
            $event = new BookingEvent;
            $event->booking_id = $locked->id;
            $event->from_status = $locked->status;
            $event->to_status = $locked->status;
            $event->changed_by = $by?->id;
            $event->note = 'Llegó una sola persona ('.($companionCame ? 'quien acompañaba' : 'quien reservó').'): se cobra a precio normal solo su servicio.';
            $event->save();

            return $locked;
        });
    }
}
