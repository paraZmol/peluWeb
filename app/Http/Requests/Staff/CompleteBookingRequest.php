<?php

namespace App\Http\Requests\Staff;

use App\Domain\Accounting\Checkout;
use App\Enums\PaymentMethod;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The visit ends: what was charged and how. Amounts arrive in soles as
 * text ("35.50"); whether they add up is checked under the booking's lock
 * (Checkout::assertSettles), against the price stored on the server.
 */
class CompleteBookingRequest extends FormRequest
{
    private const string AMOUNT = 'regex:/^\d{1,6}([.,]\d{1,2})?$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'discount' => ['nullable', 'string', self::AMOUNT],
            'payments' => ['nullable', 'array', 'max:'.count(PaymentMethod::cases())],
            'payments.*.method' => ['required', 'distinct', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'string', self::AMOUNT],
        ];
    }

    /**
     * Null when nothing was sent: the visit is charged in full.
     */
    public function checkout(): ?Checkout
    {
        if (! $this->hasAny(['payments', 'discount'])) {
            return null;
        }

        $parts = [];

        foreach ($this->array('payments') as $payment) {
            $parts[(string) $payment['method']] = Money::toCents((string) $payment['amount']);
        }

        return new Checkout(
            discountCents: Money::toCents($this->string('discount')->toString() ?: '0'),
            parts: $parts,
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'discount' => 'descuento',
            'payments' => 'pagos',
            'payments.*.method' => 'método de pago',
            'payments.*.amount' => 'monto',
        ];
    }
}
