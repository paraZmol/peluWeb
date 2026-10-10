<?php

namespace App\Http\Requests\Owner;

use App\Concerns\SalonValidationRules;
use App\Data\Accounting\ExpenseInput;
use App\Enums\PaymentMethod;
use App\Support\CurrentSalon;
use App\Support\Images\ImageStore;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An expense: how much, how it was paid, which day (on the salon's
 * calendar, never in the future) and optionally the photo of the receipt.
 */
class ExpenseRequest extends FormRequest
{
    use SalonValidationRules;

    /** Up to S/ 999,999.99, never zero. */
    private const string AMOUNT = 'regex:/^(?!0+([.,]0{1,2})?$)\d{1,6}([.,]\d{1,2})?$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = app(CurrentSalon::class)->get()?->localNow()->toDateString() ?? now()->toDateString();

        return [
            'category' => ['nullable', 'integer'],
            'amount' => ['required', 'string', self::AMOUNT],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:'.$today],
            'description' => ['nullable', 'string', 'max:160', self::PLAIN_TEXT],
            'receipt' => ['nullable', ...ImageStore::RULES],
            'remove_receipt' => ['nullable', 'boolean'],
        ];
    }

    public function expense(): ExpenseInput
    {
        return new ExpenseInput(
            categoryId: $this->filled('category') ? $this->integer('category') : null,
            amountCents: Money::toCents($this->string('amount')->toString()),
            method: $this->enum('method', PaymentMethod::class) ?? PaymentMethod::Other,
            localDate: $this->string('date')->toString(),
            description: $this->filled('description') ? $this->string('description')->toString() : null,
            receipt: $this->file('receipt'),
            removeReceipt: $this->boolean('remove_receipt'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category' => 'categoría', 'amount' => 'monto', 'method' => 'método de pago',
            'date' => 'fecha', 'description' => 'descripción', 'receipt' => 'foto del comprobante',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Escribe un monto mayor que cero, con dos decimales como máximo.',
            'date.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }
}
