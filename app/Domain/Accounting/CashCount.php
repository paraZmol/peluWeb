<?php

namespace App\Domain\Accounting;

use App\Enums\PaymentMethod;

/**
 * What moved during a cash session, per payment method. Pure: céntimos in,
 * céntimos out.
 */
final readonly class CashCount
{
    /**
     * @param  array<string, int>  $in  money received by PaymentMethod value (deposits and charges)
     * @param  array<string, int>  $out  money handed out by PaymentMethod value (refunds and expenses)
     */
    public function __construct(
        public int $openingCashCents,
        public array $in,
        public array $out,
    ) {}

    public function inCents(PaymentMethod $method): int
    {
        return $this->in[$method->value] ?? 0;
    }

    public function outCents(PaymentMethod $method): int
    {
        return $this->out[$method->value] ?? 0;
    }

    public function netCents(PaymentMethod $method): int
    {
        return $this->inCents($method) - $this->outCents($method);
    }

    /**
     * What the drawer should hold: the float plus the cash that came in,
     * minus the cash that left. Negative if more left than there was.
     */
    public function expectedCashCents(): int
    {
        return $this->openingCashCents + $this->netCents(PaymentMethod::Cash);
    }
}
