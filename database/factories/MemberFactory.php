<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'office_id' => Office::factory(),
            'nama_lengkap' => $this->faker->name,
            'jabatan' => $this->faker->jobTitle,
            'nomor_telepon' => $this->faker->phoneNumber,
            'alamat' => $this->faker->address,
        ];
    }
}
