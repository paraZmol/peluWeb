<?php

namespace App\Http\Resources;

use App\Domain\Accounting\CashCount;
use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A cash session on the salon's clock. With a count it also carries what
 * moved per method (the open session); without it, only the frozen totals.
 *
 * @mixin CashSession
 */
class CashSessionResource extends JsonResource
{
    public function __construct(CashSession $session, private readonly string $timezone, private readonly ?CashCount $count = null)
    {
        parent::__construct($session);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $difference = $this->differenceCents();

        return [
            'id' => $this->id,
            'openedAt' => $this->opened_at->setTimezone($this->timezone)->format('Y-m-d H:i'),
            'closedAt' => $this->closed_at?->setTimezone($this->timezone)->format('Y-m-d H:i'),
            'openingCash' => Money::toAmount($this->opening_cash_cents),
            'expectedCash' => $this->expectedCash(),
            'countedCash' => $this->counted_cash_cents === null ? null : Money::toAmount($this->counted_cash_cents),
            // Signed céntimos: negative when cash is missing.
            'differenceCents' => $difference,
            'note' => $this->note,
            'methods' => $this->count === null ? [] : $this->methods($this->count),
        ];
    }

    /**
     * Signed céntimos as text ("-12.50"): the drawer can come up short.
     */
    private function expectedCash(): ?string
    {
        $cents = $this->count?->expectedCashCents() ?? $this->expected_cash_cents;

        return $cents === null ? null : ($cents < 0 ? '-' : '').Money::toAmount(abs($cents));
    }

    /**
     * @return list<array{value: string, label: string, in: string, out: string, netCents: int}>
     */
    private function methods(CashCount $count): array
    {
        $rows = [];

        foreach (PaymentMethod::cases() as $method) {
            if ($count->inCents($method) === 0 && $count->outCents($method) === 0 && $method !== PaymentMethod::Cash) {
                continue;
            }

            $rows[] = [
                'value' => $method->value,
                'label' => $method->label(),
                'in' => Money::toAmount($count->inCents($method)),
                'out' => Money::toAmount($count->outCents($method)),
                'netCents' => $count->netCents($method),
            ];
        }

        return $rows;
    }
}
