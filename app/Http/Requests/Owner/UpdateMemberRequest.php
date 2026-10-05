<?php

namespace App\Http\Requests\Owner;

use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(SalonRole::class)],
            'permissions' => ['array'],
            'permissions.*' => ['distinct', Rule::enum(SalonPermission::class)],
        ];
    }

    /**
     * @return list<SalonPermission>
     */
    public function permissions(): array
    {
        /** @var list<string> $values */
        $values = $this->validated('permissions', []);

        return array_map(SalonPermission::from(...), $values);
    }
}
