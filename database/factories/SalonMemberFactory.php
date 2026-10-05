<?php

namespace Database\Factories;

use App\Enums\SalonPermission;
use App\Enums\SalonRole;
use App\Models\Salon;
use App\Models\SalonMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalonMember>
 */
class SalonMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'user_id' => User::factory(),
            'role' => SalonRole::Staff,
            'permissions' => [],
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => ['role' => SalonRole::Owner]);
    }

    public function withPermissions(SalonPermission ...$permissions): static
    {
        return $this->state(fn (array $attributes) => ['permissions' => $permissions]);
    }
}
