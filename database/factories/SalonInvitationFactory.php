<?php

namespace Database\Factories;

use App\Enums\SalonRole;
use App\Models\Salon;
use App\Models\SalonInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SalonInvitation>
 */
class SalonInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => SalonRole::Staff,
            'permissions' => [],
            'token_hash' => SalonInvitation::hashToken(Str::random(40)),
            'expires_at' => now()->addDays(SalonInvitation::VALID_DAYS),
        ];
    }

    /**
     * Use a known plain token so tests can accept the invitation.
     */
    public function withToken(string $plainToken): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => SalonInvitation::hashToken($plainToken),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => ['accepted_at' => now()]);
    }
}
