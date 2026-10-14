<?php

namespace App\Data\Promotions;

use App\Enums\PromotionMechanic;

/**
 * What the owner filled in on a promotion's form, already validated
 * against their salon.
 */
final readonly class PromotionDraft
{
    /**
     * @param  string  $card  the card they started from: discount, gift, quiet_hours, season or first_visit
     * @param  list<int>  $staffIds  only with these professionals; empty = all of the team
     * @param  bool  $newClientsOnly  "Primera visita": only for someone who never booked at the salon
     * @param  list<int>  $serviceIds  services of this salon
     * @param  string  $startsOn  "Y-m-d" on the salon's calendar
     * @param  list<int>|null  $weekdays  ISO weekdays; null = every day
     * @param  string|null  $name  their own title; null = the one the system writes
     * @param  string|null  $overline  the occasion of a season campaign
     */
    public function __construct(
        public string $card,
        public PromotionMechanic $mechanic,
        public array $serviceIds,
        public string $startsOn,
        public string $endsOn,
        public ?int $percent = null,
        public ?int $amountCents = null,
        public ?int $giftServiceId = null,
        public ?array $weekdays = null,
        public ?int $fromMinute = null,
        public ?int $toMinute = null,
        public ?int $quota = null,
        public bool $oncePerClient = false,
        public ?string $name = null,
        public ?string $overline = null,
        public bool $newClientsOnly = false,
        public array $staffIds = [],
    ) {}
}
