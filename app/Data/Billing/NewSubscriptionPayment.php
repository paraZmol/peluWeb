<?php

namespace App\Data\Billing;

use App\Enums\PaymentMethod;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\User;

/**
 * A payment the platform admin received and is about to record.
 */
final readonly class NewSubscriptionPayment
{
    public function __construct(
        public Salon $salon,
        public Plan $plan,
        /** 1, or 12 for a year (paid as 10). */
        public int $months,
        public int $amountCents,
        public PaymentMethod $method,
        public User $recordedBy,
        public ?string $note = null,
    ) {}
}
