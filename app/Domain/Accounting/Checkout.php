<?php

namespace App\Domain\Accounting;

use App\Enums\PaymentMethod;
use App\Exceptions\PaymentException;

/**
 * How a visit is settled when it ends. Pure: céntimos in, céntimos out.
 *
 *   charged = price − discount
 *   due     = charged − deposit already paid + tip
 *
 * The parts are what the client hands over, one per method, and must add
 * up to exactly `due`.
 */
final readonly class Checkout
{
    public const int MAX_PARTS = 3;

    /**
     * @param  array<string, int>  $parts  céntimos received by PaymentMethod value; zero amounts are dropped
     */
    public function __construct(
        public int $discountCents = 0,
        public int $tipCents = 0,
        public array $parts = [],
    ) {}

    /**
     * Full price, no discount, no tip, all of it in one method.
     */
    public static function inFull(PaymentMethod $method, int $priceCents, int $depositPaidCents): self
    {
        return new self(parts: [$method->value => max(0, $priceCents - $depositPaidCents)]);
    }

    public function chargedCents(int $priceCents): int
    {
        return $priceCents - $this->discountCents;
    }

    public function dueCents(int $priceCents, int $depositPaidCents): int
    {
        return $this->chargedCents($priceCents) - $depositPaidCents + $this->tipCents;
    }

    /**
     * @return array<string, int> only the methods that actually received money
     */
    public function receivedParts(): array
    {
        return array_filter($this->parts, fn (int $cents): bool => $cents > 0);
    }

    /**
     * @throws PaymentException when the numbers do not hold together
     */
    public function assertSettles(int $priceCents, int $depositPaidCents): void
    {
        if ($this->discountCents < 0 || $this->tipCents < 0 || min([0, ...array_values($this->parts)]) < 0) {
            throw PaymentException::negativeAmount();
        }

        // A deposit already taken cannot exceed what the visit ends up costing.
        if ($this->discountCents > $priceCents - $depositPaidCents) {
            throw PaymentException::discountTooLarge();
        }

        // A tip larger than the visit itself is a typo, or a way to inflate the till.
        if ($this->tipCents > $priceCents) {
            throw PaymentException::tipTooLarge();
        }

        if (count($this->receivedParts()) > self::MAX_PARTS) {
            throw PaymentException::tooManyParts(self::MAX_PARTS);
        }

        $due = $this->dueCents($priceCents, $depositPaidCents);

        if (array_sum($this->parts) !== $due) {
            throw PaymentException::doesNotAddUp($due, array_sum($this->parts));
        }
    }
}
