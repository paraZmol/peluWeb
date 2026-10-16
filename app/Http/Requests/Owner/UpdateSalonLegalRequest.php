<?php

namespace App\Http\Requests\Owner;

use App\Concerns\SalonValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Who provides the service and where the salon's own Libro de
 * Reclamaciones is (requirement P7). Everything optional.
 */
class UpdateSalonLegalRequest extends FormRequest
{
    use SalonValidationRules;

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['legal_name', 'ruc', 'contact_email', 'complaints_book_url'])
            ->mapWithKeys(fn (string $field) => [$field => is_string($this->input($field)) ? (trim($this->input($field)) ?: null) : $this->input($field)])
            ->all());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['nullable', 'string', 'min:2', 'max:200', self::PLAIN_TEXT],
            'ruc' => ['nullable', 'string', 'regex:/^(10|15|17|20)\d{9}$/'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            // Only https: the link is shown on the public page.
            'complaints_book_url' => ['nullable', 'string', 'max:500', 'url:https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ruc.regex' => 'El RUC tiene 11 números y empieza con 10, 15, 17 o 20.',
            'complaints_book_url.url' => 'Pega el enlace completo, que empiece con https://',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'legal_name' => 'nombre o razón social',
            'ruc' => 'RUC',
            'contact_email' => 'correo del salón',
            'complaints_book_url' => 'enlace a tu Libro de Reclamaciones',
        ];
    }
}
