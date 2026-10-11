<?php

namespace App\Http\Requests\Owner;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The salon's answer to a review, or why it reports one.
 */
class ReviewModerationRequest extends FormRequest
{
    private const int REASON_MIN_LENGTH = 10;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return $this->routeIs('reviews.report')
            ? ['reason' => ['required', 'string', 'min:'.self::REASON_MIN_LENGTH, 'max:'.Review::REPORT_MAX_LENGTH]]
            // Empty takes the answer back.
            : ['reply' => ['nullable', 'string', 'max:'.Review::COMMENT_MAX_LENGTH]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reply' => 'respuesta', 'reason' => 'motivo'];
    }

    /**
     * The trimmed text of a field, or null when it was left empty.
     */
    public function text(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }
}
