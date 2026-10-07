<?php

namespace App\Http\Requests\Owner;

use App\Concerns\SalonValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HolidayRequest extends FormRequest
{
    use SalonValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // "yesterday" leaves room for salons whose day started before the server's.
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:yesterday', 'before:+2 years'],
            'name' => ['required', 'string', 'min:2', 'max:80', self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['date' => 'fecha', 'name' => 'motivo'];
    }
}
