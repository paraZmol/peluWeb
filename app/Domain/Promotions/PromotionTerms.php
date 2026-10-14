<?php

namespace App\Domain\Promotions;

use App\Data\Booking\QuoteItem;
use App\Enums\PromotionMechanic;
use Carbon\CarbonImmutable;

/**
 * The rules of one promotion, as plain values: when a visit qualifies and
 * what it does to the price of each service. No database, no clock.
 * See docs/promociones.md ("Reglas comunes" and "Fichas").
 */
final readonly class PromotionTerms
{
    /** Prices with a promotion go down to the nearest 10 céntimos. */
    private const int ROUNDING_CENTS = 10;

    /**
     * @param  list<int>  $serviceIds  the services it applies to (for a gift: any of them earns it)
     * @param  string  $startsOn  first day of visits it covers, "Y-m-d" on the salon's calendar
     * @param  list<int>|null  $weekdays  ISO weekdays (1 = Monday); null = every day
     * @param  int|null  $fromMinute  minutes from midnight the visit may start in; null = any time
     * @param  bool  $newClientsOnly  only for someone who never had a booking at the salon (checked by ApplyPromotion)
     */
    public function __construct(
        public int $id,
        public string $name,
        public PromotionMechanic $mechanic,
        public array $serviceIds,
        public string $startsOn,
        public string $endsOn,
        public ?array $weekdays = null,
        public ?int $fromMinute = null,
        public ?int $toMinute = null,
        public ?int $percent = null,
        public ?int $amountCents = null,
        public ?int $giftServiceId = null,
        public bool $newClientsOnly = false,
    ) {}

    /**
     * Whether a visit starting then qualifies: by the date of the visit,
     * and by the time it starts when the promotion names hours.
     *
     * @param  CarbonImmutable  $localStart  on the salon's clock
     */
    public function covers(CarbonImmutable $localStart): bool
    {
        $day = $localStart->toDateString();

        if ($day < $this->startsOn || $day > $this->endsOn) {
            return false;
        }

        if ($this->weekdays !== null && ! in_array($localStart->dayOfWeekIso, $this->weekdays, true)) {
            return false;
        }

        if ($this->fromMinute === null || $this->toMinute === null) {
            return true;
        }

        $minute = $localStart->hour * 60 + $localStart->minute;

        // "22:00 to 02:00" wraps past midnight.
        return $this->fromMinute <= $this->toMinute
            ? $minute >= $this->fromMinute && $minute < $this->toMinute
            : $minute >= $this->fromMinute || $minute < $this->toMinute;
    }

    /**
     * What the client saves on these services; 0 when it does not apply to
     * them.
     *
     * @param  list<QuoteItem>  $items
     */
    public function savedCents(array $items): int
    {
        return array_sum(array_map(
            fn (QuoteItem $before, QuoteItem $after): int => $before->priceCents - $after->priceCents,
            $items,
            $this->price($items),
        ));
    }

    /**
     * The same services with what each one costs under this promotion.
     * Their normal price stays on each of them.
     *
     * @param  list<QuoteItem>  $items
     * @return list<QuoteItem>
     */
    public function price(array $items): array
    {
        return match ($this->mechanic) {
            PromotionMechanic::Gift => $this->gift($items),
            PromotionMechanic::Discount => $this->percent !== null ? $this->percentOff($items) : $this->amountOff($items),
            PromotionMechanic::Combo => $this->combo($items),
        };
    }

    /**
     * Only with every service of the combo in the booking. Its saving is
     * shared among them by price, like a manual discount; the last one
     * takes the remainder so it adds up exactly. None may end at S/ 0:
     * then it does not apply.
     *
     * @param  list<QuoteItem>  $items
     * @return list<QuoteItem>
     */
    private function combo(array $items): array
    {
        $inCombo = array_values(array_filter($items, fn (QuoteItem $item): bool => in_array($item->serviceId, $this->serviceIds, true)));
        $saving = (int) $this->amountCents;
        $total = array_sum(array_map(fn (QuoteItem $item): int => $item->priceCents, $inCombo));
        $hasAll = count(array_unique(array_map(fn (QuoteItem $item): int => $item->serviceId, $inCombo))) === count($this->serviceIds);

        if (! $hasAll || $saving <= 0 || $total <= $saving) {
            return $items;
        }

        $shares = [];
        $left = $saving;

        foreach ($inCombo as $position => $item) {
            $share = $position === count($inCombo) - 1 ? $left : intdiv($saving * $item->priceCents, $total);
            $shares[$item->serviceId] = $share;
            $left -= $share;

            if ($share >= $item->priceCents) {
                return $items;
            }
        }

        return array_map(
            fn (QuoteItem $item): QuoteItem => isset($shares[$item->serviceId]) ? $item->chargedAt($item->priceCents - $shares[$item->serviceId]) : $item,
            $items,
        );
    }

    /**
     * The percentage goes to each included service.
     *
     * @param  list<QuoteItem>  $items
     * @return list<QuoteItem>
     */
    private function percentOff(array $items): array
    {
        return array_map(function (QuoteItem $item): QuoteItem {
            if (! in_array($item->serviceId, $this->serviceIds, true)) {
                return $item;
            }

            $discounted = intdiv($item->priceCents * (100 - (int) $this->percent), 100);
            $rounded = $discounted - $discounted % self::ROUNDING_CENTS;

            // Rounding must not turn a discount into a gift.
            return $item->chargedAt($rounded > 0 ? $rounded : $discounted);
        }, $items);
    }

    /**
     * A fixed amount comes off once per booking, from the first included
     * service that costs more than it: a professional's own price may be
     * lower than the catalogue's, and "S/ 10 less" must not make it free.
     *
     * @param  list<QuoteItem>  $items
     * @return list<QuoteItem>
     */
    private function amountOff(array $items): array
    {
        $isUsed = false;

        return array_map(function (QuoteItem $item) use (&$isUsed): QuoteItem {
            $isIncluded = in_array($item->serviceId, $this->serviceIds, true);

            if ($isUsed || ! $isIncluded || $item->priceCents <= (int) $this->amountCents) {
                return $item;
            }

            $isUsed = true;
            $discounted = $item->priceCents - (int) $this->amountCents;
            $rounded = $discounted - $discounted % self::ROUNDING_CENTS;

            return $item->chargedAt($rounded > 0 ? $rounded : $discounted);
        }, $items);
    }

    /**
     * The gift is free only in a booking that also has one of the services
     * that earn it; alone, it costs what it costs.
     *
     * @param  list<QuoteItem>  $items
     * @return list<QuoteItem>
     */
    private function gift(array $items): array
    {
        $earnsIt = array_filter($items, fn (QuoteItem $item): bool => $item->serviceId !== $this->giftServiceId && in_array($item->serviceId, $this->serviceIds, true));

        if ($earnsIt === []) {
            return $items;
        }

        // The gift has one price: an option of it (long hair, a bigger
        // treatment) is another service, at its own price.
        return array_map(
            fn (QuoteItem $item): QuoteItem => $item->serviceId === $this->giftServiceId && $item->variantId === null ? $item->chargedAt(0) : $item,
            $items,
        );
    }
}
