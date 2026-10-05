<?php

namespace Database\Factories;

use App\Enums\BusinessType;
use App\Enums\Clientele;
use App\Enums\SalonRole;
use App\Enums\SalonStatus;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Salon>
 */
class SalonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'business_type' => fake()->randomElement(BusinessType::cases()),
            'clientele' => fake()->randomElement(Clientele::cases()),
            'phone' => '9'.fake()->numerify('########'),
            'timezone' => 'America/Lima',
            'currency' => 'PEN',
            'status' => SalonStatus::Trial,
            'trial_ends_at' => now()->addDays(Salon::TRIAL_DAYS),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SalonStatus::Suspended]);
    }

    /**
     * Attach the given user (or a new one) as owner.
     */
    public function ownedBy(?User $owner = null): static
    {
        return $this->afterCreating(function (Salon $salon) use ($owner): void {
            SalonMember::factory()
                ->for($salon)
                ->for($owner ?? User::factory()->create())
                ->create(['role' => SalonRole::Owner]);
        });
    }
}
