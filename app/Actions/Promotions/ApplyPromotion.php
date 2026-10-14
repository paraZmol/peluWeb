<?php

namespace App\Actions\Promotions;

use App\Data\Booking\BookingQuote;
use App\Domain\Promotions\PromotionPicker;
use App\Enums\BookingStatus;
use App\Enums\PromotionStatus;
use App\Models\Booking;
use App\Models\Promotion;
use App\Models\Salon;
use App\Models\SalonClient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Prices a quote under the salon's best promotion for that visit, if any
 * applies and still has room. Names the salon explicitly: visitors booking
 * on the public page have no current salon.
 *
 * With $underLock it runs inside the booking's transaction: the promotion
 * row is locked and its places are counted with locking reads. A plain
 * read there would count from the snapshot the transaction took before it
 * waited for the lock, and hand the last place to two bookings at once.
 * Lock order: professional → client → booking → promotion → salon.
 */
class ApplyPromotion
{
    /**
     * @param  SalonClient|null  $client  null, or new and unsaved, for someone who never booked here
     */
    public function handle(Salon $salon, ?SalonClient $client, BookingQuote $quote, CarbonImmutable $startsAt, bool $underLock = false): BookingQuote
    {
        $localStart = $startsAt->setTimezone($salon->timezone);

        return $this->pick($this->running($salon, $localStart->toDateString()), $client, $quote, $localStart, $underLock);
    }

    /**
     * What the best promotion would take off this selection at each of
     * those starts of one day, for the time picker to mark them. Not under
     * any lock: the booking itself counts the places again.
     *
     * @param  list<int>  $starts  Unix seconds (UTC), all on the same day of the salon's calendar
     * @return array<int, array{name: string, cents: int, firstVisit: bool}> by start; a start without a promotion is left out
     */
    public function forStarts(Salon $salon, ?SalonClient $client, BookingQuote $quote, array $starts): array
    {
        if ($starts === []) {
            return [];
        }

        $first = CarbonImmutable::createFromTimestampUTC($starts[0])->setTimezone($salon->timezone);
        // Places do not depend on the time of day: counted once for all of them.
        $running = $this->running($salon, $first->toDateString())
            ->filter(fn (Promotion $promotion): bool => $this->hasRoom($promotion, $client, false));
        $marked = [];

        foreach ($running->isEmpty() ? [] : $starts as $start) {
            $priced = $this->pick($running, $client, $quote, CarbonImmutable::createFromTimestampUTC($start)->setTimezone($salon->timezone), false, countPlaces: false);

            if ($priced->promotion !== null) {
                $marked[$start] = ['name' => $priced->promotion->name, 'cents' => $priced->promotionCents(), 'firstVisit' => $priced->promotion->newClientsOnly];
            }
        }

        return $marked;
    }

    /**
     * The salon's promotions on offer for visits of that day.
     *
     * @return Collection<int, Promotion> by id
     */
    private function running(Salon $salon, string $day): Collection
    {
        return Promotion::withoutGlobalScope('salon')
            ->where('salon_id', $salon->id)
            ->runningOn($day)
            ->with(['services', 'giftService', 'staff'])
            ->get()
            ->filter(fn (Promotion $promotion): bool => $promotion->isIntact())
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, Promotion>  $running
     */
    private function pick(Collection $running, ?SalonClient $client, BookingQuote $quote, CarbonImmutable $localStart, bool $underLock, bool $countPlaces = true): BookingQuote
    {
        // One of some of the team holds only with one of them chosen.
        $running = $running->filter(fn (Promotion $promotion): bool => $promotion->terms()->allowsStaff($quote->chosenStaffId));

        if ($running->isEmpty()) {
            return $quote;
        }

        $ranked = PromotionPicker::ranked(
            array_values($running->map(fn (Promotion $promotion) => $promotion->terms())->all()),
            $quote->items,
            $localStart,
        );

        foreach ($ranked as $terms) {
            /** @var Promotion $promotion */
            $promotion = $running->get($terms->id);

            if (! $countPlaces || $this->hasRoom($promotion, $client, $underLock)) {
                return $quote->withPromotion($terms);
            }
        }

        return $quote;
    }

    /**
     * Whether a booking whose hold ran out may still be confirmed at its
     * promotion's price: its place went back, and someone may have taken
     * it. Runs inside the booking's transaction, with the booking locked.
     */
    public function stillHasRoomFor(Booking $booking): bool
    {
        if ($booking->promotion_id === null) {
            return true;
        }

        $promotion = Promotion::withoutGlobalScope('salon')->withTrashed()->whereKey($booking->promotion_id)->lockForUpdate()->first();

        if ($promotion === null) {
            return true;
        }

        $others = fn (): Builder => $this->taken($promotion, underLock: true)->where('id', '!=', $booking->id);

        // A first visit no longer: while its hold was out, the client
        // booked again (and that booking may have taken the price too).
        // Locking reads, without the client's row: it comes before the
        // booking in the lock order, and the booking is already held.
        $ownOthers = fn (): Builder => $this->live(Booking::withoutGlobalScope('salon')->where('salon_client_id', $booking->salon_client_id), underLock: true)
            ->where('id', '!=', $booking->id);

        return ($promotion->quota === null || $others()->count() < $promotion->quota)
            && (! $promotion->once_per_client || $others()->where('salon_client_id', $booking->salon_client_id)->count() === 0)
            && (! $promotion->new_clients_only || ! $ownOthers()->exists());
    }

    /**
     * Whether anyone could still get it: for what is advertised to the
     * public, not for a booking (that one counts again, under the lock).
     */
    public function hasPlacesLeft(Promotion $promotion): bool
    {
        return $promotion->quota === null || $this->taken($promotion, false)->count() < $promotion->quota;
    }

    /**
     * Places left, and not already used by this client when it is one
     * each. A booking that was cancelled or whose hold ran out gives its
     * place back; a visit that happened, or was missed, keeps it.
     */
    private function hasRoom(Promotion $promotion, ?SalonClient $client, bool $underLock): bool
    {
        // Asked before the promotion is locked: the client's row comes first.
        $isNewcomer = $promotion->new_clients_only ? $this->isNewcomer($client, $underLock) : null;

        if ($isNewcomer === false) {
            return false;
        }

        if ($underLock) {
            // The row as it is now: it may have been paused, or its places
            // changed, since it was read above.
            $promotion = Promotion::withoutGlobalScope('salon')->whereKey($promotion->id)->lockForUpdate()->first();

            // Turned into a first-visit one meanwhile: not given unasked.
            if ($promotion === null || $promotion->status !== PromotionStatus::Published || ($promotion->new_clients_only && $isNewcomer !== true)) {
                return false;
            }
        }

        if ($promotion->quota === null && ! $promotion->once_per_client) {
            return true;
        }

        if ($promotion->quota !== null && $this->taken($promotion, $underLock)->count() >= $promotion->quota) {
            return false;
        }

        return ! $promotion->once_per_client
            || $client === null
            || ! $client->exists
            || $this->taken($promotion, $underLock)->where('salon_client_id', $client->id)->count() === 0;
    }

    /**
     * Never booked at this salon: no booking of theirs other than a
     * cancelled one or one whose hold ran out. A visit missed counts, and
     * so does one still ahead (two "first" visits are not two).
     */
    private function isNewcomer(?SalonClient $client, bool $underLock): bool
    {
        if ($client === null || ! $client->exists) {
            return true;
        }

        if ($underLock) {
            // Serialises two first bookings of one person at once.
            SalonClient::withoutGlobalScope('salon')->whereKey($client->id)->lockForUpdate()->first();
        }

        return ! $this->live(Booking::withoutGlobalScope('salon')->whereIn('salon_client_id', $this->samePerson($client, $underLock)), $underLock)->exists();
    }

    /**
     * Their records at this salon: one person may have two (booked once
     * by phone at the desk and once online), found by account or phone.
     * Under the lock a locking read: a plain one would read the snapshot
     * taken before this booking waited, and miss a record committed since.
     *
     * @return list<int>
     */
    private function samePerson(SalonClient $client, bool $underLock): array
    {
        $others = SalonClient::withoutGlobalScope('salon')
            ->where('salon_id', $client->salon_id)
            ->where(fn (Builder $same) => $same
                ->whereKey($client->id)
                ->when($client->user_id !== null, fn (Builder $query) => $query->orWhere('user_id', $client->user_id))
                ->when(filled($client->phone), fn (Builder $query) => $query->orWhere('phone', $client->phone)))
            ->when($underLock, fn (Builder $query) => $query->sharedLock())
            ->pluck('id')
            ->all();

        return array_values(array_unique([$client->id, ...$others]));
    }

    /**
     * The bookings holding a place of it.
     *
     * @return Builder<Booking>
     */
    private function taken(Promotion $promotion, bool $underLock): Builder
    {
        return $this->live(Booking::withoutGlobalScope('salon')->where('promotion_id', $promotion->id), $underLock);
    }

    /**
     * Only bookings that still hold something: not cancelled, not expired,
     * and not a pending one whose hold ran out.
     *
     * @param  Builder<Booking>  $bookings
     * @return Builder<Booking>
     */
    private function live(Builder $bookings, bool $underLock): Builder
    {
        return $bookings
            ->whereNotIn('status', [BookingStatus::Cancelled, BookingStatus::Expired])
            ->where(fn (Builder $live) => $live
                ->where('status', '!=', BookingStatus::Pending)
                ->orWhereNull('hold_expires_at')
                ->orWhere('hold_expires_at', '>', CarbonImmutable::now()))
            // Locking: sees what other bookings committed while this one waited.
            ->when($underLock, fn (Builder $query) => $query->sharedLock());
    }
}
