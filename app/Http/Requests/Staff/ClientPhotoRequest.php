<?php

namespace App\Http\Requests\Staff;

use App\Enums\ClientPhotoKind;
use App\Support\Images\ImageStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', ...ImageStore::RULES],
            'kind' => ['required', 'string', Rule::enum(ClientPhotoKind::class)],
            // A picture of a person is only kept if they agreed to it.
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => 'Marca que la persona autorizó la foto para uso interno.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['photo' => 'foto', 'kind' => 'momento'];
    }
}
