<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Casilla 1: "Leí y acepto los Términos y condiciones y la Política de
 * privacidad." Never ticked beforehand; checked here, not only in the form.
 */
class AcceptLegalTermsRequest extends FormRequest
{
    public const string FIELD = 'terms';

    public const string MESSAGE = 'Para continuar, marca que leíste y aceptas los Términos y la Política de privacidad.';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [self::FIELD => ['accepted']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [self::FIELD.'.accepted' => self::MESSAGE];
    }
}
