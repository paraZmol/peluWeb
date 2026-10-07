<?php

namespace App\Http\Requests\Staff;

use App\Concerns\ParsesBookingTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The team moves a booking: to a start picked from the offered ones, or to
 * a day and time on the salon's clock (dropped on the agenda's grid).
 */
class MoveBookingRequest extends FormRequest
{
    use ParsesBookingTime;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start' => ['nullable', 'required_without:date', 'integer', 'min:1', 'max:4102444800'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'required_with:date', 'date_format:H:i'],
            'staff' => ['nullable', 'integer'],
            'stay' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The new start. A day and time are read on the given salon clock, so
     * the browser's timezone never decides when an appointment is.
     */
    public function startsAtIn(string $timezone): CarbonImmutable
    {
        if ($this->filled('date')) {
            $local = CarbonImmutable::createFromFormat('!Y-m-d H:i', $this->string('date').' '.$this->string('time'), $timezone);

            return ($local ?: CarbonImmutable::now())->utc();
        }

        return $this->startsAt();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['start' => 'horario', 'date' => 'día', 'time' => 'hora', 'staff' => 'profesional'];
    }
}
