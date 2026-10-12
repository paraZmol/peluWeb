<?php

namespace App\Http\Requests\Staff;

use App\Concerns\SalonValidationRules;
use App\Models\WalkIn;
use App\Support\CurrentSalon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalkInRequest extends FormRequest
{
    use SalonValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Another salon's service or professional is "not found", like its URLs.
        $salonId = app(CurrentSalon::class)->get()?->id;

        return [
            'name' => ['required', 'string', 'max:'.WalkIn::NAME_MAX_LENGTH, self::PLAIN_TEXT],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('salon_id', $salonId)->where('is_active', true)],
            'staff_id' => ['nullable', 'integer', Rule::exists('staff', 'id')->where('salon_id', $salonId)->where('is_active', true)->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'service_id' => 'servicio', 'staff_id' => 'profesional'];
    }
}
