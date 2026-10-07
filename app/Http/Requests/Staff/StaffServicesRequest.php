<?php

namespace App\Http\Requests\Staff;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StaffServicesRequest extends FormRequest
{
    private const int MAX_SERVICES = 300;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'services' => ['present', 'array', 'list', 'max:'.self::MAX_SERVICES],
            'services.*.service_id' => ['required', 'integer', 'distinct'],
            'services.*.active_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'services.*.price' => ['nullable', 'string', 'regex:/^\d{1,5}([.,]\d{1,2})?$/'],
        ];
    }

    /**
     * @return list<array{service_id: int, active_minutes: int|null, price_cents: int|null}>
     */
    public function entries(): array
    {
        /** @var list<array{service_id: int|string, active_minutes?: int|string|null, price?: string|null}> $services */
        $services = $this->validated('services');

        return array_map(fn (array $service): array => [
            'service_id' => (int) $service['service_id'],
            'active_minutes' => isset($service['active_minutes']) ? (int) $service['active_minutes'] : null,
            'price_cents' => isset($service['price']) ? Money::toCents($service['price']) : null,
        ], $services);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'services.*.active_minutes' => 'tiempo',
            'services.*.price' => 'precio',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['services.*.price.regex' => 'Escribe el precio en soles, por ejemplo 35 o 35.50.'];
    }
}
