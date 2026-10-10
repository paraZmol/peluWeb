<?php

namespace App\Http\Requests\Staff;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A deposit received or given back: only the method is chosen, the amount
 * is the one stored on the booking.
 */
class PaymentMethodRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return $this->enum('method', PaymentMethod::class) ?? PaymentMethod::Other;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['method' => 'método de pago'];
    }
}
