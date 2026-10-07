<?php

namespace App\Http\Requests\Public;

use App\Concerns\ParsesBookingSelection;
use App\Concerns\ParsesBookingTime;
use App\Concerns\SalonValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    use ParsesBookingSelection, ParsesBookingTime, SalonValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->selectionRules(),
            ...$this->startRules(),
            'note' => ['nullable', 'string', 'max:255', self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['services' => 'servicios', 'start' => 'horario', 'note' => 'nota'];
    }
}
