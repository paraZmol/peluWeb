<?php

namespace App\Http\Requests\Public;

use App\Concerns\ParsesBookingSelection;
use App\Concerns\ParsesBookingTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
{
    use ParsesBookingSelection, ParsesBookingTime;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...$this->selectionRules(), ...$this->dayRules()];
    }
}
