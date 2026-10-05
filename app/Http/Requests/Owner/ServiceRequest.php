<?php

namespace App\Http\Requests\Owner;

use App\Enums\ServiceCategory;
use App\Enums\ServiceTarget;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public const int MAX_VARIANTS = 6;

    private const string PRICE_RULE = 'regex:/^\d{1,5}([.,]\d{1,2})?$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120', 'regex:/^[^<>\[\]{}]+$/u'],
            'category' => ['required', Rule::enum(ServiceCategory::class)],
            'target' => ['required', Rule::enum(ServiceTarget::class)],
            'description' => ['nullable', 'string', 'max:255', 'regex:/^[^<>\[\]{}]+$/u'],
            'price' => ['required', 'string', self::PRICE_RULE],
            'active_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'idle_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'finish_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'is_addon' => ['boolean'],
            'requires_evaluation' => ['boolean'],
            'requires_patch_test' => ['boolean'],
            'variants' => ['array', 'max:'.self::MAX_VARIANTS],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.label' => ['required', 'string', 'max:40', 'regex:/^[^<>\[\]{}]+$/u'],
            'variants.*.price' => ['required', 'string', self::PRICE_RULE],
            'variants.*.extra_minutes' => ['nullable', 'integer', 'min:-60', 'max:240'],
        ];
    }

    /**
     * Validated data mapped to Service attributes (prices in céntimos).
     *
     * @return array<string, mixed>
     */
    public function serviceAttributes(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'category' => $this->enum('category', ServiceCategory::class),
            'target' => $this->enum('target', ServiceTarget::class),
            'description' => $this->validated('description'),
            'price_cents' => Money::toCents($this->validated('price')),
            'active_minutes' => (int) $this->validated('active_minutes'),
            'idle_minutes' => (int) ($this->validated('idle_minutes') ?? 0),
            'finish_minutes' => (int) ($this->validated('finish_minutes') ?? 0),
            'is_addon' => $this->boolean('is_addon'),
            'requires_evaluation' => $this->boolean('requires_evaluation'),
            'requires_patch_test' => $this->boolean('requires_patch_test'),
        ];
    }

    /**
     * @return list<array{id: int|null, label: string, price_cents: int, extra_minutes: int}>
     */
    public function variants(): array
    {
        /** @var list<array{id?: int|null, label: string, price: string, extra_minutes?: int|null}> $variants */
        $variants = $this->validated('variants', []);

        return array_map(fn (array $variant): array => [
            'id' => isset($variant['id']) ? (int) $variant['id'] : null,
            'label' => trim($variant['label']),
            'price_cents' => Money::toCents($variant['price']),
            'extra_minutes' => (int) ($variant['extra_minutes'] ?? 0),
        ], $variants);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'price' => 'precio',
            'active_minutes' => 'tiempo de trabajo',
            'idle_minutes' => 'tiempo de espera',
            'finish_minutes' => 'tiempo final',
            'variants.*.label' => 'nombre de la opción',
            'variants.*.price' => 'precio de la opción',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.regex' => 'Escribe el precio en soles, por ejemplo 35 o 35.50.',
            'variants.*.price.regex' => 'Escribe el precio en soles, por ejemplo 35 o 35.50.',
        ];
    }
}
