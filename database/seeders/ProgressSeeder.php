<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Progress;
use App\Models\Member;
use App\Models\Attendance;
use Carbon\Carbon;

class ProgressSeeder extends Seeder
{
    private $activities = [
        // Backend Activities
        'Membuat REST API untuk module authentication',
        'Implementasi CRUD untuk data member',
        'Membuat migration dan model untuk database',
        'Testing API endpoint menggunakan Postman',
        'Debugging error pada fitur attendance',
        'Implementasi validasi form input',
        'Integrasi WhatsApp Bot dengan webhook',
        'Membuat dokumentasi API dengan Swagger',
        'Refactoring code untuk improve performance',
        'Setup environment production di VPS',
        
        // Frontend Activities
        'Membuat tampilan dashboard admin',
        'Implementasi form input data member',
        'Styling UI menggunakan Tailwind CSS',
        'Integrasi frontend dengan backend API',
        'Testing responsive design di berbagai device',
        'Membuat komponen reusable untuk table',
        'Implementasi fitur filter dan search',
        'Debugging error pada form validation',
        'Membuat chart untuk statistik kehadiran',
        'Setup deployment frontend ke hosting',
        
        // General Activities
        'Meeting dengan supervisor pembimbing',
        'Belajar framework Laravel dan Vue.js',
        'Review code dengan team developer',
        'Membuat dokumentasi user manual',
        'Training tentang best practice development',
        'Mengikuti daily standup meeting',
        'Research teknologi baru untuk project',
        'Membuat laporan progress mingguan',
        'Testing integrasi antar module',
        'Koordinasi dengan team UI/UX Designer',
        
        // Database & DevOps
        'Optimasi query database untuk performa',
        'Setup backup database otomatis',
        'Konfigurasi server nginx dan PHP-FPM',
        'Implementasi caching dengan Redis',
        'Monitoring performance aplikasi',
        'Setup CI/CD pipeline dengan GitHub Actions',
        'Troubleshooting error di production',
        'Migrasi database ke server baru',
    ];

    public function run(): void
    {
        $members = Member::where('status_aktif', true)->get();
        
        // Generate progress data untuk 30 hari terakhir
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now()->subDay(); // sampai kemarin
        
        foreach ($members as $member) {
            // Get attendance data untuk member ini
            $attendances = Attendance::where('member_id', $member->id)
                ->whereBetween('tanggal', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->where('status', 'hadir')
                ->get();
            
            // Buat progress report hanya untuk hari yang hadir
            foreach ($attendances as $attendance) {
                // 80% kemungkinan ada progress report (kadang lupa input)
                if (rand(1, 100) <= 80) {
                    $date = Carbon::parse($attendance->tanggal);
                    
                    // Generate 1-3 aktivitas per hari
                    $numActivities = rand(1, 3);
                    $selectedActivities = [];
                    
                    for ($i = 0; $i < $numActivities; $i++) {
                        $activity = $this->activities[array_rand($this->activities)];
                        if (!in_array($activity, $selectedActivities)) {
                            $selectedActivities[] = $activity;
                        }
                    }
                    
                    $description = "Kegiatan hari ini:\n";
                    foreach ($selectedActivities as $index => $activity) {
                        $description .= ($index + 1) . ". " . $activity . "\n";
                    }
                    
                    Progress::create([
                        'member_id' => $member->id,
                        'tanggal' => $attendance->tanggal,
                        'description' => trim($description),
                        'created_at' => $date->copy()->setTime(rand(17, 18), rand(0, 59), 0),
                        'updated_at' => $date->copy()->setTime(rand(17, 18), rand(0, 59), 0),
                    ]);
                }
            }
        }
        
        // Tambah progress untuk hari ini (member yang sudah check-in)
        $todayAttendances = Attendance::whereDate('tanggal', Carbon::today())
            ->whereNotNull('check_in_time')
            ->get();
        
        foreach ($todayAttendances->take(2) as $attendance) {
            Progress::create([
                'member_id' => $attendance->member_id,
                'tanggal' => Carbon::today()->format('Y-m-d'),
                'description' => "Kegiatan hari ini:\n1. " . $this->activities[array_rand($this->activities)] . "\n2. " . $this->activities[array_rand($this->activities)],
            ]);
        }
    }
}
