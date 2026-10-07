<?php

namespace App\Http\Requests\Client;

use App\Concerns\ParsesBookingTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Asks for the times an existing booking could move to on one day.
 */
class BookingDayRequest extends FormRequest
{
    use ParsesBookingTime;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...$this->dayRules(), 'staff' => ['nullable', 'integer']];
    }
}
