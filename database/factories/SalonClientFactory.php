<?php

namespace Database\Factories;

use App\Models\Salon;
use App\Models\SalonClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalonClient>
 */
class SalonClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'name' => fake()->name(),
            'phone' => '9'.fake()->numerify('########'),
        ];
    }

    public function vip(): static
    {
        return $this->state(fn (array $attributes) => ['is_vip' => true]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id, 'name' => $user->name]);
    }
}
