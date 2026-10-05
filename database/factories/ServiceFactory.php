<?php

namespace Database\Factories;

use App\Enums\ServiceCategory;
use App\Enums\ServiceTarget;
use App\Models\Salon;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'category' => ServiceCategory::MenCut,
            'name' => fake()->randomElement(['Degradado medio', 'Corte clásico', 'Perfilado de barba']),
            'target' => ServiceTarget::Men,
            'price_cents' => fake()->numberBetween(20, 60) * 100,
            'active_minutes' => 45,
        ];
    }

    public function withIdle(int $active, int $idle, int $finish): static
    {
        return $this->state(fn (array $attributes) => [
            'active_minutes' => $active,
            'idle_minutes' => $idle,
            'finish_minutes' => $finish,
        ]);
    }
}
