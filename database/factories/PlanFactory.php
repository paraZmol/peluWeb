<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('plan-????'),
            'name' => fake()->word(),
            'price_cents' => 5000,
            'founder_price_cents' => null,
            'max_staff' => 3,
            'is_public' => true,
            'position' => 9,
        ];
    }
}
