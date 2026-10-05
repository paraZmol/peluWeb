<?php

namespace App\Http\Requests\Owner;

use App\Concerns\NormalizesPhone;
use App\Enums\BusinessType;
use App\Enums\Clientele;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalonRequest extends FormRequest
{
    use NormalizesPhone;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Letters, digits and everyday punctuation only: the name goes into
            // emails and pages, so no markup or link syntax.
            'name' => ['required', 'string', 'min:2', 'max:120', "regex:/^[\\pL\\pN\\s'&.,·:\\-]+$/u"],
            'business_type' => ['required', Rule::enum(BusinessType::class)],
            'clientele' => ['required', Rule::enum(Clientele::class)],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'El nombre solo puede tener letras, números, espacios y signos como . , & \' -',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre del salón',
            'business_type' => 'tipo de negocio',
            'clientele' => 'público',
            'phone' => 'WhatsApp del salón',
        ];
    }
}
