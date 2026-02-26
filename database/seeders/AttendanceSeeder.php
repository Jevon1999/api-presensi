<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\Member;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $members = Member::where('status_aktif', true)->get();
        
        // Generate attendance data untuk 30 hari terakhir
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now()->subDay(); // sampai kemarin
        
        foreach ($members as $member) {
            $currentDate = $startDate->copy();
            
            while ($currentDate <= $endDate) {
                // Skip weekend (Sabtu & Minggu)
                if ($currentDate->isWeekend()) {
                    $currentDate->addDay();
                    continue;
                }
                
                // 85% kemungkinan hadir, sisanya random izin/sakit/alpha
                $randomStatus = $this->generateRandomStatus();
                
                $attendance = [
                    'member_id' => $member->id,
                    'tanggal' => $currentDate->format('Y-m-d'),
                    'status' => $randomStatus,
                    'created_at' => $currentDate->copy()->setTime(8, 0, 0),
                    'updated_at' => $currentDate->copy()->setTime(17, 0, 0),
                ];
                
                // Jika hadir, tambahkan waktu check-in dan check-out
                if ($randomStatus === 'hadir') {
                    // Check-in antara 07:30 - 09:00
                    $checkInHour = rand(7, 8);
                    $checkInMinute = $checkInHour == 7 ? rand(30, 59) : rand(0, 59);
                    $attendance['check_in_time'] = sprintf('%02d:%02d:00', $checkInHour, $checkInMinute);
                    
                    // Check-out antara 16:30 - 18:00
                    $checkOutHour = rand(16, 17);
                    $checkOutMinute = $checkOutHour == 16 ? rand(30, 59) : rand(0, 59);
                    $attendance['check_out_time'] = sprintf('%02d:%02d:00', $checkOutHour, $checkOutMinute);
                }
                
                Attendance::create($attendance);
                
                $currentDate->addDay();
            }
        }
        
        // Tambah attendance hari ini untuk beberapa member (sebagian sudah check-in, sebagian belum)
        $membersToday = $members->take(3);
        foreach ($membersToday as $key => $member) {
            $today = Carbon::today();
            
            // Member pertama sudah check-in dan check-out
            if ($key == 0) {
                Attendance::create([
                    'member_id' => $member->id,
                    'tanggal' => $today->format('Y-m-d'),
                    'check_in_time' => '08:15:00',
                    'check_out_time' => '17:30:00',
                    'status' => 'hadir',
                ]);
            }
            // Member kedua baru check-in
            elseif ($key == 1) {
                Attendance::create([
                    'member_id' => $member->id,
                    'tanggal' => $today->format('Y-m-d'),
                    'check_in_time' => '08:30:00',
                    'check_out_time' => null,
                    'status' => 'hadir',
                ]);
            }
            // Member ketiga belum absen (akan jadi alpha jika tidak absen)
        }
    }
    
    private function generateRandomStatus(): string
    {
        $rand = rand(1, 100);
        
        if ($rand <= 85) {
            return 'hadir'; // 85% hadir
        } elseif ($rand <= 92) {
            return 'izin'; // 7% izin
        } elseif ($rand <= 97) {
            return 'sakit'; // 5% sakit
        } else {
            return 'alpha'; // 3% alpha
        }
    }
}
