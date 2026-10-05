<?php

namespace App\Http\Requests\Owner;

use App\Support\Images\ImageStore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Logo, cover and gallery uploads all go through the same image rules.
 */
class SalonImageRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', ...ImageStore::RULES],
            'caption' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['image' => 'imagen', 'caption' => 'descripción'];
    }
}
