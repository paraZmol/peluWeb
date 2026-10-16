<?php

namespace App\Http\Requests\Public;

use App\Enums\ComplaintItemType;
use App\Enums\ComplaintKind;
use App\Enums\IdentityDocument;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A sheet of the platform's Libro de Reclamaciones, filed without an
 * account. Box 19 of docs/legal/consentimientos.md must be ticked.
 */
class StoreComplaintRequest extends FormRequest
{
    /** Highest amount the form takes, in soles: beyond that it is a typo. */
    private const int MAX_CLAIMED_SOLES = 100000;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(ComplaintKind::class)],
            'consumer_name' => ['required', 'string', 'min:3', 'max:150'],
            'consumer_address' => ['required', 'string', 'min:5', 'max:255'],
            'document_type' => ['required', Rule::enum(IdentityDocument::class)],
            'document_number' => ['required', 'string', $this->input('document_type') === IdentityDocument::Dni->value ? 'regex:/^\d{8}$/' : 'regex:/^[A-Za-z0-9]{6,12}$/'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{6,20}$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'is_minor' => ['boolean'],
            'guardian_name' => ['nullable', 'required_if_accepted:is_minor', 'string', 'min:3', 'max:150'],
            'item_type' => ['required', Rule::enum(ComplaintItemType::class)],
            'item_description' => ['required', 'string', 'min:3', 'max:255'],
            'claimed_amount' => ['nullable', 'regex:/^\d+([.,]\d{1,2})?$/', 'numeric', 'max:'.self::MAX_CLAIMED_SOLES],
            'detail' => ['required', 'string', 'min:10', 'max:3000'],
            'request' => ['required', 'string', 'min:5', 'max:2000'],
            'consent' => ['accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('claimed_amount');

        if (is_string($amount)) {
            $this->merge(['claimed_amount' => str_replace(',', '.', trim($amount)) ?: null]);
        }
    }

    /**
     * The validated form as the columns of a sheet.
     *
     * @return array<string, mixed>
     */
    public function sheet(): array
    {
        $amount = $this->validated('claimed_amount');

        return [
            ...$this->safe()->only([
                'kind', 'consumer_name', 'consumer_address', 'document_type', 'document_number',
                'phone', 'email', 'item_type', 'item_description', 'detail', 'request',
            ]),
            'guardian_name' => $this->boolean('is_minor') ? $this->validated('guardian_name') : null,
            'claimed_amount_cents' => $amount === null ? null : Money::toCents((string) $amount),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_number.regex' => $this->input('document_type') === IdentityDocument::Dni->value
                ? 'El DNI tiene 8 números.'
                : 'Escribe el número del documento, sin espacios ni guiones.',
            'phone.regex' => 'Escribe un teléfono solo con números.',
            'guardian_name.required_if_accepted' => 'Si eres menor de edad, escribe el nombre de tu madre, padre o tutor.',
            'claimed_amount.regex' => 'Escribe el monto en soles, por ejemplo 35.50.',
            'consent.accepted' => 'Marca la casilla para poder enviar la hoja.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kind' => 'tipo',
            'consumer_name' => 'nombre completo',
            'consumer_address' => 'domicilio',
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'phone' => 'teléfono',
            'email' => 'correo',
            'guardian_name' => 'nombre de tu madre, padre o tutor',
            'item_type' => 'producto o servicio',
            'item_description' => 'descripción',
            'claimed_amount' => 'monto reclamado',
            'detail' => 'detalle',
            'request' => 'pedido',
        ];
    }
}
