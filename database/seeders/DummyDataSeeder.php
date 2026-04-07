<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\Office;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 3 offices
        $offices = Office::factory()->count(3)->create();

        // For each office, create 5 members
        $offices->each(function ($office) {
            Member::factory()->count(5)->create([
                'office_id' => $office->id,
            ])->each(function ($member) {
                // For each member, create 10 attendance records for the last month
                Attendance::factory()->count(10)->create([
                    'member_id' => $member->id,
                ]);
            });
        });
    }
}
