<?php

namespace App\Http\Requests\Owner;

use App\Concerns\SalonValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSalonLocationRequest extends FormRequest
{
    use SalonValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'address' => ['nullable', 'string', 'max:160', self::PLAIN_TEXT],
            'district' => ['nullable', 'string', 'max:80', self::PLAIN_TEXT],
            'city' => ['nullable', 'string', 'max:80', self::PLAIN_TEXT],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->salonAttributes();
    }
}
