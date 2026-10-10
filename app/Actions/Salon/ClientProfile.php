<?php

namespace App\Actions\Salon;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SalonClient;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * A client's file as the team reads it: what is coming, what they have
 * had done, how reliable they are and what to keep in mind attending them.
 */
class ClientProfile
{
    private const int UPCOMING = 5;

    private const int HISTORY = 30;

    /**
     * @param  bool  $showsMoney  what was charged is only for who handles the salon's money
     * @return array<string, mixed>
     */
    public function handle(Salon $salon, SalonClient $client, bool $showsMoney): array
    {
        $now = CarbonImmutable::now();
        $visit = fn (Booking $booking): array => $this->visit($booking, $salon, $showsMoney);

        $upcoming = $this->bookings($client)->holdingSlot($now)->where('ends_at', '>', $now)
            ->orderBy('starts_at')->limit(self::UPCOMING)->get();
        $history = $this->bookings($client)
            ->whereIn('status', [BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::NoShow])
            ->orderByDesc('starts_at')->limit(self::HISTORY)->get();

        return [
            'stats' => $this->stats($client, $salon, $showsMoney),
            'upcoming' => $upcoming->map($visit)->all(),
            'history' => $history->map($visit)->all(),
            'isHistoryTruncated' => $history->count() === self::HISTORY,
        ];
    }

    /**
     * @return array{visits: int, cancelled: int, noShows: int, firstVisit: string|null, lastVisit: string|null, spent: string|null}
     */
    private function stats(SalonClient $client, Salon $salon, bool $showsMoney): array
    {
        $completed = Booking::withoutGlobalScope('salon')
            ->where('salon_client_id', $client->id)
            ->where('status', BookingStatus::Completed)
            ->selectRaw('COUNT(*) as visits, MIN(starts_at) as first_at, MAX(starts_at) as last_at, COALESCE(SUM(charged_cents), 0) as spent')
            ->toBase()->first();
        $day = fn (?string $utc): ?string => $utc === null
            ? null
            : CarbonImmutable::parse($utc, 'UTC')->setTimezone($salon->timezone)->format('Y-m-d');

        return [
            'visits' => (int) ($completed->visits ?? 0),
            'cancelled' => Booking::withoutGlobalScope('salon')
                ->where('salon_client_id', $client->id)
                ->where('status', BookingStatus::Cancelled)
                ->count(),
            'noShows' => $client->no_show_count,
            'firstVisit' => $day($completed->first_at ?? null),
            'lastVisit' => $day($completed->last_at ?? null),
            'spent' => $showsMoney ? Money::toAmount((int) ($completed->spent ?? 0)) : null,
        ];
    }

    /**
     * @return Builder<Booking>
     */
    private function bookings(SalonClient $client): Builder
    {
        return Booking::withoutGlobalScope('salon')
            ->where('salon_client_id', $client->id)
            ->with(['services', 'staff' => fn ($query) => $query->withoutGlobalScope('salon')->withTrashed()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function visit(Booking $booking, Salon $salon, bool $showsMoney): array
    {
        $start = $booking->starts_at->setTimezone($salon->timezone);

        return [
            'id' => $booking->id,
            'date' => $start->format('Y-m-d'),
            'time' => $start->format('H:i'),
            'status' => $booking->status->value,
            'statusLabel' => $booking->status->label(),
            'staffName' => $booking->staff->name,
            'services' => $booking->services->pluck('name')->all(),
            'note' => $booking->client_note,
            'charged' => $showsMoney && $booking->charged_cents !== null ? Money::toAmount($booking->charged_cents) : null,
        ];
    }
}
