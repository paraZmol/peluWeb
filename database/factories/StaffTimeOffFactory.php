<?php

namespace Database\Factories;

use App\Enums\TimeOffReason;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffTimeOff>
 */
class StaffTimeOffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => Staff::factory(),
            'salon_id' => fn (array $attributes) => Staff::withoutGlobalScope('salon')->whereKey($attributes['staff_id'])->firstOrFail()->salon_id,
            'starts_at' => now()->addDays(3)->startOfDay(),
            'ends_at' => now()->addDays(5)->startOfDay(),
            'reason' => TimeOffReason::Vacation,
        ];
    }
}
