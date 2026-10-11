<?php

namespace App\Http\Requests\Client;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
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
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:'.Review::MAX_RATING],
            'comment' => ['nullable', 'string', 'max:'.Review::COMMENT_MAX_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['rating' => 'calificación', 'comment' => 'comentario'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['rating.required' => 'Elige de 1 a 5 estrellas.'];
    }
}
