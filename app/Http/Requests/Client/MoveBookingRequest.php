<?php

namespace App\Http\Requests\Client;

use App\Concerns\ParsesBookingTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Moves an existing booking to a start picked from the offered ones.
 */
class MoveBookingRequest extends FormRequest
{
    use ParsesBookingTime;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...$this->startRules(), 'staff' => ['nullable', 'integer']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['start' => 'horario', 'staff' => 'profesional'];
    }
}
