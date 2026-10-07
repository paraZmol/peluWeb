<?php

namespace App\Http\Requests\Owner;

use App\Concerns\SalonValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StaffRequest extends FormRequest
{
    use SalonValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80', self::PLAIN_TEXT],
            'title' => ['nullable', 'string', 'max:60', self::PLAIN_TEXT],
            'is_active' => ['boolean'],
            'user_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Fields that were not sent are left as they are: a partial update
     * (e.g. only the name) must not reactivate a paused professional.
     *
     * @return array{name: string, title: string|null, is_active?: bool}
     */
    public function staffAttributes(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'title' => $this->string('title')->trim()->toString() ?: null,
            ...($this->has('is_active') ? ['is_active' => $this->boolean('is_active')] : []),
        ];
    }

    /**
     * The account to link; $current when the field was not sent at all.
     */
    public function userId(?int $current = null): ?int
    {
        return $this->has('user_id') ? ($this->integer('user_id') ?: null) : $current;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'title' => 'especialidad', 'user_id' => 'cuenta'];
    }
}
