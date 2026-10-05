<?php

namespace App\Http\Requests\Owner;

use App\Concerns\NormalizesPhone;
use App\Concerns\SalonValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalonRequest extends FormRequest
{
    use NormalizesPhone, SalonValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->salonIdentityRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->salonMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->salonAttributes();
    }
}
