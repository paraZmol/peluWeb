<?php

namespace App\Http\Requests\Public;

use App\Concerns\ParsesBookingSelection;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
{
    use ParsesBookingSelection;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->selectionRules(),
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * The requested calendar day on the given salon clock.
     */
    public function localDate(string $timezone): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $this->string('date')->toString(), $timezone)
            ?: CarbonImmutable::now($timezone);
    }
}
