<?php

namespace App\Http\Requests\Owner;

use App\Enums\SalonTheme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalonThemeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['theme' => ['required', Rule::enum(SalonTheme::class)]];
    }

    public function theme(): SalonTheme
    {
        return SalonTheme::from($this->string('theme')->toString());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['theme' => 'diseño'];
    }
}
