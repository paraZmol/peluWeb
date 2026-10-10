<?php

namespace App\Data\Accounting;

use App\Models\Salon;
use Carbon\CarbonImmutable;

/**
 * A range of days on a salon's calendar, both included, and the UTC
 * instants that bound it.
 */
final readonly class Period
{
    private const string DAY = '/\A\d{4}-\d{2}-\d{2}\z/';

    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    /**
     * From what was asked, or this month up to today when it is missing,
     * malformed or backwards. Never beyond today nor longer than a year.
     */
    public static function inSalon(Salon $salon, ?string $from, ?string $to): self
    {
        $today = $salon->localNow()->startOfDay();
        $end = self::day($to, $salon->timezone) ?? $today;
        $end = $end->greaterThan($today) ? $today : $end;
        $start = self::day($from, $salon->timezone) ?? $end->startOfMonth();

        if ($start->greaterThan($end) || $start->lessThan($end->subYear())) {
            $start = $end->startOfMonth();
        }

        return new self($start, $end);
    }

    public function startsAtUtc(): CarbonImmutable
    {
        return $this->from->utc();
    }

    /**
     * The first instant after the period: compare with `<`.
     */
    public function endsBeforeUtc(): CarbonImmutable
    {
        return $this->to->addDay()->utc();
    }

    private static function day(?string $value, string $timezone): ?CarbonImmutable
    {
        if ($value === null || preg_match(self::DAY, $value) !== 1 || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
            return null;
        }

        return CarbonImmutable::parse("{$value} 00:00", $timezone);
    }
}
