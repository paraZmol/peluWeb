<?php

namespace App\Http\Requests\Staff;

use App\Data\Salon\ClientNotes;
use App\Models\SalonClient;
use Illuminate\Foundation\Http\FormRequest;

class ClientNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $max = 'max:'.SalonClient::NOTES_MAX_LENGTH;

        return [
            'notes' => ['nullable', 'string', $max],
            'formula_notes' => ['nullable', 'string', $max],
            'care_notes' => ['nullable', 'string', $max],
            // Health data is only kept with the person's signed authorization.
            'care_consent' => ['boolean', 'accepted_if:has_care_notes,true'],
            // Under age: the sheet carries a parent's or guardian's signature.
            'care_minor' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'care_consent.accepted_if' => 'Para guardar alergias o reacciones, la persona debe firmar la autorización y el salón conservarla.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $care = $this->input('care_notes');

        $this->merge(['has_care_notes' => is_string($care) && trim($care) !== '']);
    }

    public function notes(): ClientNotes
    {
        return new ClientNotes(
            notes: $this->filled('notes') ? $this->string('notes')->toString() : null,
            formulaNotes: $this->filled('formula_notes') ? $this->string('formula_notes')->toString() : null,
            careNotes: $this->filled('care_notes') ? $this->string('care_notes')->toString() : null,
            hasCareConsent: $this->boolean('care_consent'),
            isSignedByGuardian: $this->boolean('care_minor'),
        );
    }
}
