<?php

namespace App\Http\Requests\Owner;

use App\Concerns\NormalizesPhone;
use App\Concerns\SalonValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSalonProfileRequest extends FormRequest
{
    use NormalizesPhone, SalonValidationRules {
        NormalizesPhone::prepareForValidation as normalizePhone;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();

        $instagram = $this->input('instagram');

        if (is_string($instagram)) {
            $this->merge(['instagram' => ltrim(trim($instagram), '@') ?: null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->salonIdentityRules(),
            'description' => ['nullable', 'string', 'max:600'],
            'instagram' => ['nullable', 'string', 'regex:/^[A-Za-z0-9._]{1,30}$/'],
        ];
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
