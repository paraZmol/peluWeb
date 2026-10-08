<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            // Shown in the list of signed-in devices.
            'device_name' => ['required', 'string', 'max:60', 'regex:/^[\pL\pN\s._\-]+$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'correo', 'password' => 'contraseña', 'device_name' => 'nombre del dispositivo'];
    }
}
