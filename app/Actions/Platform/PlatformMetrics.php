<?php

namespace App\Actions\Platform;

use App\Enums\BookingStatus;
use App\Enums\SalonStatus;
use App\Enums\SubscriptionState;
use App\Models\Booking;
use App\Models\Salon;
use App\Models\SubscriptionPayment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The platform admin's numbers (PLAN 7.4): salons by where they stand,
 * what the platform was paid this month and last, and bookings this
 * month across every salon. Months are Lima's (decision D2).
 */
class PlatformMetrics
{
    /** Days ahead in which a trial or a paid time ending counts as "soon". */
    public const int ENDING_SOON_DAYS = 7;

    /**
     * @return array{byState: array<string, int>, paidThisMonthCents: int, paidLastMonthCents: int, bookingsThisMonth: int}
     */
    public function handle(CarbonImmutable $now): array
    {
        $month = $now->setTimezone(Salon::DEFAULT_TIMEZONE)->startOfMonth();

        return [
            'byState' => $this->byState(),
            'paidThisMonthCents' => $this->paidBetween($month, $month->addMonth()),
            'paidLastMonthCents' => $this->paidBetween($month->subMonth(), $month),
            'bookingsThisMonth' => Booking::withoutGlobalScope('salon')
                ->whereNotIn('status', [BookingStatus::Cancelled, BookingStatus::Expired])
                ->where('starts_at', '>=', $month->utc())
                ->where('starts_at', '<', $month->addMonth()->utc())
                ->count(),
        ];
    }

    /**
     * Salons whose trial or paid time ends within the next days, soonest first.
     *
     * @return Builder<Salon>
     */
    public static function endingSoon(CarbonImmutable $now): Builder
    {
        // Whole days in Lima: a trial's last day counts until it is over.
        $localNow = $now->setTimezone(Salon::DEFAULT_TIMEZONE);
        $today = $localNow->toDateString();
        $limit = $localNow->addDays(self::ENDING_SOON_DAYS)->toDateString();

        return Salon::query()
            ->where('status', '!=', SalonStatus::Suspended)
            ->where(fn (Builder $query) => $query
                ->whereBetween('paid_until', [$today, $limit])
                ->orWhere(fn (Builder $trial) => $trial->whereNull('paid_until')->whereBetween('trial_ends_at', [$localNow->startOfDay()->utc(), $localNow->addDays(self::ENDING_SOON_DAYS)->endOfDay()->utc()])))
            ->orderByRaw('coalesce(paid_until, trial_ends_at)');
    }

    /**
     * @return array<string, int>
     */
    private function byState(): array
    {
        $counts = array_fill_keys(array_map(fn (SubscriptionState $state) => $state->value, SubscriptionState::cases()), 0);

        Salon::query()->select(['id', 'status', 'timezone', 'trial_ends_at', 'paid_until'])
            ->chunkById(500, function ($salons) use (&$counts): void {
                foreach ($salons as $salon) {
                    $counts[$salon->standing()->state->value]++;
                }
            });

        return $counts;
    }

    private function paidBetween(CarbonImmutable $from, CarbonImmutable $until): int
    {
        return (int) SubscriptionPayment::withoutGlobalScope('salon')
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $until->utc())
            ->sum('amount_cents');
    }
}
