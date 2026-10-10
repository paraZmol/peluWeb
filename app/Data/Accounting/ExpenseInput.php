<?php

namespace App\Data\Accounting;

use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * What the form sends for an expense, already validated and converted.
 */
final readonly class ExpenseInput
{
    public function __construct(
        public ?int $categoryId,
        public int $amountCents,
        public PaymentMethod $method,
        /** The day it was paid, on the salon's calendar (Y-m-d). */
        public string $localDate,
        public ?string $description,
        public ?UploadedFile $receipt,
        public bool $removeReceipt,
    ) {}

    /**
     * Today's expenses happen now, so they fall inside the open cash
     * session. An earlier day gets its midday: a date without a time must
     * never slip into the neighbouring day when read back in the salon's zone.
     */
    public function paidAt(string $timezone, CarbonImmutable $now): CarbonImmutable
    {
        if ($now->setTimezone($timezone)->toDateString() === $this->localDate) {
            return $now->utc();
        }

        return CarbonImmutable::parse("{$this->localDate} 12:00", $timezone)->utc();
    }
}
