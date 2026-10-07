<?php

namespace Database\Factories;

use App\Enums\ScheduleAccess;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'name' => fake()->name(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function linkedTo(User $user): static
    {
        return $this->state(fn (array $attributes) => ['user_id' => $user->id]);
    }

    /**
     * Works the given ISO weekdays in one range.
     *
     * @param  list<int>  $weekdays
     */
    public function working(array $weekdays, string $start = '09:00', string $end = '18:00', ScheduleAccess $access = ScheduleAccess::Public): static
    {
        return $this->afterCreating(function (Staff $staff) use ($weekdays, $start, $end, $access): void {
            foreach ($weekdays as $weekday) {
                $schedule = new StaffSchedule;
                $schedule->salon_id = $staff->salon_id;
                $schedule->staff_id = $staff->id;
                $schedule->weekday = $weekday;
                $schedule->start_time = $start;
                $schedule->end_time = $end;
                $schedule->access_level = $access;
                $schedule->save();
            }
        });
    }
}
