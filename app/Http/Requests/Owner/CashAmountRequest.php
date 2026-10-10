<?php

namespace App\Http\Requests\Owner;

use App\Concerns\SalonValidationRules;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The cash in the drawer, in soles as text: the float when opening, the
 * count when closing (zero is a valid answer to both).
 */
class CashAmountRequest extends FormRequest
{
    use SalonValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:/^\d{1,6}([.,]\d{1,2})?$/'],
            'note' => ['nullable', 'string', 'max:255', self::PLAIN_TEXT],
        ];
    }

    public function cents(): int
    {
        return Money::toCents($this->string('amount')->toString());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amount' => 'monto', 'note' => 'nota'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['amount.regex' => 'Escribe un monto en soles, con dos decimales como máximo.'];
    }
}
