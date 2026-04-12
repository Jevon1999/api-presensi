<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\BotConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MarkAlphaStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:mark-alpha-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark attendance as alpha/bolos for members who didnt check-in by checkout time and have no permission';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $config = BotConfig::where('is_active', true)->first();
            
            if (!$config) {
                Log::info('Mark alpha: No active config');
                return self::SUCCESS;
            }

            // Use today's date for real-time checking
            $today = now()->format('Y-m-d');
            
            // Get all active members
            $allMembers = Member::where('status_aktif', true)->get();
            
            $alphaCount = 0;
            
            foreach ($allMembers as $member) {
                // Check if member has attendance record for today
                $attendance = Attendance::where('member_id', $member->id)
                    ->where('tanggal', $today)
                    ->first();
                
                // Alpha/Bolos logic: 
                // If checkout time has passed AND member has not checked in AND no izin/sakit
                if (!$attendance) {
                    // No attendance record at all = alpha (belum check-in)
                    Attendance::create([
                        'member_id' => $member->id,
                        'tanggal' => $today,
                        'status' => 'alpha',
                        'check_in_time' => null,
                        'check_out_time' => null,
                    ]);
                    
                    $alphaCount++;
                    
                    Log::info('Alpha status created - no check-in by checkout time', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'tanggal' => $today,
                        'reason' => 'no_attendance_record',
                    ]);
                } 
                elseif (!$attendance->check_in_time && !in_array($attendance->status, ['izin', 'sakit', 'alpha'])) {
                    // Attendance created but no check-in and no valid permission
                    // = member is bolos/alpha
                    $attendance->update(['status' => 'alpha']);
                    
                    $alphaCount++;
                    
                    Log::info('Alpha status updated - no check-in and no permission', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'tanggal' => $today,
                        'old_status' => $attendance->status,
                        'reason' => 'no_checkin_no_permission',
                    ]);
                }
                elseif (
                    !$attendance->check_in_time 
                    && in_array($attendance->status, ['hadir']) 
                    && $attendance->created_at->diffInMinutes(now()) > 60
                ) {
                    // Attendance record exists with 'hadir' status but no check-in after 1 hour
                    // = member likely forgot or is lazy to update
                    $attendance->update(['status' => 'alpha']);
                    
                    $alphaCount++;
                    
                    Log::info('Alpha status updated - hadir but no check-in after 1 hour', [
                        'member_id' => $member->id,
                        'member_name' => $member->nama_lengkap,
                        'tanggal' => $today,
                        'created_at' => $attendance->created_at,
                        'reason' => 'delayed_no_checkin',
                    ]);
                }
            }
            
            Log::info('Mark alpha status completed', [
                'total_marked_alpha' => $alphaCount,
                'tanggal' => $today,
            ]);

            $this->info("Alpha marking completed. {$alphaCount} members marked as alpha.");
            return self::SUCCESS;

        } catch (\Exception $e) {
            Log::error('Mark alpha status error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
