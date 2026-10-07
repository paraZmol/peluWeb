<?php

namespace App\Http\Requests\Public;

use App\Concerns\ParsesBookingSelection;
use App\Concerns\SalonValidationRules;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    use ParsesBookingSelection, SalonValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->selectionRules(),
            // Unix seconds (UTC), exactly as the availability endpoint offered it.
            'start' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255', self::PLAIN_TEXT],
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC($this->integer('start'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['services' => 'servicios', 'start' => 'horario', 'note' => 'nota'];
    }
}
