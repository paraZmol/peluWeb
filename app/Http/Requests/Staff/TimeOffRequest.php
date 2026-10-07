<?php

namespace App\Http\Requests\Staff;

use App\Concerns\SalonValidationRules;
use App\Enums\TimeOffReason;
use App\Models\StaffTimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TimeOffRequest extends FormRequest
{
    use SalonValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // "yesterday" leaves room for salons whose day started before the server's.
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:yesterday', 'before:+2 years'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'all_day' => ['required', 'boolean'],
            'start_time' => ['exclude_if:all_day,true', 'required', 'date_format:H:i'],
            'end_time' => ['exclude_if:all_day,true', 'required', 'date_format:H:i'],
            'reason' => ['required', Rule::enum(TimeOffReason::class)],
            'note' => ['nullable', 'string', 'max:120', self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                [$start, $end] = $this->period('UTC');

                if ($end <= $start) {
                    $validator->errors()->add('end_time', 'La hora de regreso debe ser posterior a la de salida.');
                }

                if ($start->diffInDays($end) > StaffTimeOff::MAX_DAYS) {
                    $validator->errors()->add('ends_on', 'Una ausencia puede durar como máximo un año.');
                }
            },
        ];
    }

    /**
     * The period as instants, reading the dates and times in the salon's
     * timezone. A whole-day absence ends at midnight after its last day.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    public function period(string $timezone): array
    {
        // input(), not validated(): this also runs inside the after() hook.
        $startsOn = $this->string('starts_on')->toString();
        $endsOn = $this->string('ends_on')->toString();

        if ($this->boolean('all_day')) {
            return [
                CarbonImmutable::parse($startsOn, $timezone)->startOfDay(),
                CarbonImmutable::parse($endsOn, $timezone)->addDay()->startOfDay(),
            ];
        }

        return [
            CarbonImmutable::parse("{$startsOn} {$this->string('start_time')}", $timezone),
            CarbonImmutable::parse("{$endsOn} {$this->string('end_time')}", $timezone),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'starts_on' => 'desde',
            'ends_on' => 'hasta',
            'start_time' => 'hora de salida',
            'end_time' => 'hora de regreso',
            'reason' => 'motivo',
            'note' => 'nota',
        ];
    }
}
