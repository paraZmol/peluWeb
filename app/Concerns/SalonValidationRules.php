<?php

namespace App\Concerns;

use App\Enums\BusinessType;
use App\Enums\Clientele;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Rules shared by every form that edits a salon. The name and description
 * end up in emails and public pages, so no markup or link syntax.
 */
trait SalonValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|string|object>>
     */
    protected function salonIdentityRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120', "regex:/^[\\pL\\pN\\s'&.,·:\\-]+$/u"],
            'business_type' => ['required', Rule::enum(BusinessType::class)],
            'clientele' => ['required', Rule::enum(Clientele::class)],
            'phone' => ['nullable', 'string', 'regex:/^\\+?[0-9]{7,15}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function salonMessages(): array
    {
        return [
            'name.regex' => 'El nombre solo puede tener letras, números, espacios y signos como . , & \' -',
            'instagram.regex' => 'Escribe solo tu usuario de Instagram, por ejemplo @barberiadonlucho.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function salonAttributes(): array
    {
        return [
            'name' => 'nombre del salón',
            'business_type' => 'tipo de negocio',
            'clientele' => 'público',
            'phone' => 'WhatsApp del salón',
            'description' => 'descripción',
            'address' => 'dirección',
            'district' => 'distrito',
            'city' => 'ciudad',
        ];
    }
}
