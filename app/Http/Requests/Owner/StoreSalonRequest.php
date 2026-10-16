<?php

namespace App\Http\Requests\Owner;

use App\Concerns\NormalizesPhone;
use App\Concerns\SalonValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalonRequest extends FormRequest
{
    use NormalizesPhone, SalonValidationRules;

    /** Casilla 5: the salon terms and the data processing annex. */
    public const string TERMS_FIELD = 'salon_terms';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->salonIdentityRules(),
            self::TERMS_FIELD => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->salonMessages(),
            self::TERMS_FIELD.'.accepted' => 'Para crear tu salón, acepta los Términos para salones y el Encargo de tratamiento de datos.',
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
