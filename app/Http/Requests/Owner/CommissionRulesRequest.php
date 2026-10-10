<?php

namespace App\Http\Requests\Owner;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A professional's commission: the general percent and, per service, an
 * optional percent or fixed amount (soles as text).
 */
class CommissionRulesRequest extends FormRequest
{
    private const int MAX_SERVICES = 300;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'percent' => ['required', 'integer', 'min:0', 'max:100'],
            'services' => ['nullable', 'array', 'max:'.self::MAX_SERVICES],
            'services.*.id' => ['required', 'integer', 'distinct'],
            'services.*.percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'services.*.amount' => ['nullable', 'string', 'regex:/^\d{1,5}([.,]\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<int, array{percent: int|null, cents: int|null}>
     */
    public function byService(): array
    {
        $rules = [];

        foreach ($this->array('services') as $service) {
            $amount = (string) ($service['amount'] ?? '');
            $percent = $service['percent'] ?? null;

            $rules[(int) $service['id']] = [
                'percent' => $percent === null || $percent === '' ? null : (int) $percent,
                'cents' => $amount === '' ? null : Money::toCents($amount),
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['percent' => 'porcentaje', 'services.*.percent' => 'porcentaje del servicio', 'services.*.amount' => 'monto fijo'];
    }
}
