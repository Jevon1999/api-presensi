<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'date' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'check_in_time' => $this->faker->time('H:i:s'),
            'check_out_time' => $this->faker->optional(0.7)->time('H:i:s'), // 70% chance of having a check-out time
            'status' => $this->faker->randomElement(['present', 'absent', 'leave']),
        ];
    }
}
