<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\SalonMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * Deleting the account cascades its memberships: a salon whose only
     * owner leaves would be left with nobody to run it.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $soleOwned = $this->user()->memberships()
                    ->with('salon')
                    ->get()
                    ->filter(fn (SalonMember $member) => $member->isOwner()
                        && $member->salon !== null
                        && $member->salon->ownersCount() <= 1);

                if ($soleOwned->isNotEmpty()) {
                    $names = $soleOwned->map(fn (SalonMember $member) => $member->salon?->name)->join(', ');

                    $validator->errors()->add(
                        'password',
                        "Eres el único dueño de {$names}. Asigna otro dueño en Equipo antes de eliminar tu cuenta.",
                    );
                }
            },
        ];
    }
}
