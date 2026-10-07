<?php

namespace App\Http\Requests\Owner;

use App\Concerns\SalonValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalonBookingRequest extends FormRequest
{
    use SalonValidationRules;

    /** Grids that divide the hour evenly, so the picker reads naturally. */
    public const array SLOT_STEPS = [5, 10, 15, 20, 30, 60];

    private const int WEEK_IN_MINUTES = 10080;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'accepts_online_bookings' => ['required', 'boolean'],
            'min_lead_minutes' => ['required', 'integer', 'min:0', 'max:'.self::WEEK_IN_MINUTES],
            'max_days_ahead' => ['required', 'integer', 'min:1', 'max:365'],
            'slot_step_minutes' => ['required', 'integer', Rule::in(self::SLOT_STEPS)],
            'cancel_window_hours' => ['required', 'integer', 'min:0', 'max:168'],
            'deposit_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'vip_deposit_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'hold_minutes' => ['required', 'integer', 'min:10', 'max:1440'],
            'payment_instructions' => ['nullable', 'string', 'max:255', self::PLAIN_TEXT],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'min_lead_minutes' => 'anticipación mínima',
            'max_days_ahead' => 'días de anticipación',
            'slot_step_minutes' => 'intervalo de horarios',
            'cancel_window_hours' => 'plazo para cancelar',
            'deposit_percent' => 'seña',
            'vip_deposit_percent' => 'seña para VIP',
            'hold_minutes' => 'tiempo para enviar la seña',
            'payment_instructions' => 'datos para la seña',
        ];
    }
}
