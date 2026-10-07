<?php

namespace App\Http\Requests\Owner;

use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(SalonRole::class)],
            'permissions' => ['array'],
            'permissions.*' => ['distinct', Rule::enum(SalonPermission::class)],
            'staff_id' => ['nullable', 'integer'],
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'correo',
            'role' => 'rol',
            'permissions' => 'permisos',
        ];
    }
}
