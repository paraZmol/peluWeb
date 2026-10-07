<?php

namespace App\Http\Requests\Staff;

use App\Domain\Schedule\TimeRange;
use App\Enums\ScheduleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WeeklyScheduleRequest extends FormRequest
{
    public const int MAX_RANGES_PER_DAY = 4;

    public const int MINUTE_STEP = 5;

    public const int MIN_RANGE_MINUTES = 15;

    private const int DAYS = 7;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ranges' => ['present', 'array', 'list', 'max:'.self::DAYS * self::MAX_RANGES_PER_DAY],
            'ranges.*.weekday' => ['required', 'integer', 'between:1,'.self::DAYS],
            'ranges.*.start' => ['required', 'date_format:H:i'],
            'ranges.*.end' => ['required', 'date_format:H:i'],
            'ranges.*.access_level' => ['required', Rule::enum(ScheduleAccess::class)],
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

                $perDay = [];

                /** @var array<int, array{weekday: int|string, start: string, end: string}> $ranges */
                $ranges = $this->input('ranges', []);

                foreach ($ranges as $index => $range) {
                    $start = TimeRange::toMinutes($range['start']);
                    $end = TimeRange::toMinutes($range['end']);

                    if ($end - $start < self::MIN_RANGE_MINUTES) {
                        $validator->errors()->add("ranges.{$index}.end", 'La hora de salida debe ser al menos 15 minutos después de la de entrada.');
                    }

                    if ($start % self::MINUTE_STEP !== 0 || $end % self::MINUTE_STEP !== 0) {
                        $validator->errors()->add("ranges.{$index}.start", 'Usa horas en saltos de 5 minutos.');
                    }

                    $perDay[(int) $range['weekday']] = ($perDay[(int) $range['weekday']] ?? 0) + 1;
                }

                if ($perDay !== [] && max($perDay) > self::MAX_RANGES_PER_DAY) {
                    $validator->errors()->add('ranges', 'Cada día admite hasta '.self::MAX_RANGES_PER_DAY.' horarios.');
                }
            },
        ];
    }

    /**
     * @return list<array{weekday: int, start: string, end: string, access: ScheduleAccess}>
     */
    public function ranges(): array
    {
        /** @var list<array{weekday: int|string, start: string, end: string, access_level: string}> $ranges */
        $ranges = $this->validated('ranges');

        return array_map(fn (array $range): array => [
            'weekday' => (int) $range['weekday'],
            'start' => $range['start'],
            'end' => $range['end'],
            'access' => ScheduleAccess::from($range['access_level']),
        ], $ranges);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ranges.*.start' => 'hora de entrada',
            'ranges.*.end' => 'hora de salida',
            'ranges.*.access_level' => 'tipo de horario',
        ];
    }
}
