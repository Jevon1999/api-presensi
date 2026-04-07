<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Office;
use App\Models\Member;
use App\Models\Attendance;
use App\Models\Progress;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            DummyDataSeeder::class,
        ]);
    }
}