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
     * 
     * Urutan seeding penting karena ada foreign key dependencies:
     * 1. Users (untuk created_by di members)
     * 2. Offices & Locations (untuk office_id di members)
     * 3. BotConfig (independent)
     * 4. Members (butuh users & offices)
     * 5. Attendances (butuh members)
     * 6. Progresses (butuh members & attendances)
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            OfficeSeeder::class,
            BotConfigSeeder::class,
            MemberSeeder::class,
            AttendanceSeeder::class,
            ProgressSeeder::class,
        ]);
        
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->info('');
        $this->command->info('📊 Summary:');
        $this->command->info('   - Users: ' . User::count());
        $this->command->info('   - Offices: ' . Office::count());
        $this->command->info('   - Members: ' . Member::count());
        $this->command->info('   - Attendances: ' . Attendance::count());
        $this->command->info('   - Progresses: ' . Progress::count());
        $this->command->info('');
        $this->command->info('🔑 Test Accounts:');
        $this->command->info('   Admin: admin@test.com / password');
        $this->command->info('   User: user@test.com / password');
        $this->command->info('   Super Admin: admin@globalintermedia.online / admin123');
    }
}