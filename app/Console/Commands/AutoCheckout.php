<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\BotConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoCheckout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:auto-checkout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto checkout for members who forgot to checkout (3 hours after checkout threshold)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $config = BotConfig::where('is_active', true)->first();
            
            if (!$config || !$config->reminder_check_out_time) {
                Log::info('Auto checkout: No active config or checkout time');
                return self::SUCCESS;
            }

            $today = now()->format('Y-m-d');
            
            // Calculate timeout: 3 hours after configured checkout time
            $checkoutThreshold = Carbon::parse($config->reminder_check_out_time);
            $autoCheckoutTime = $checkoutThreshold->copy()->addHours(3);
            
            // Get all attendances from today that:
            // 1. Have check_in_time (user checked in)
            // 2. Don't have check_out_time (user forgot to checkout)
            // 3. Check-in was more than 3 hours after checkout threshold
            $attendances = Attendance::where('tanggal', $today)
                ->whereNotNull('check_in_time')
                ->whereNull('check_out_time')
                ->get();

            $autoCheckoutCount = 0;
            
            foreach ($attendances as $attendance) {
                $checkInTime = Carbon::parse($attendance->check_in_time);
                
                // Calculate if we should auto-checkout
                // Use the auto-checkout time as the check-out time
                if ($autoCheckoutTime->isPast() || Carbon::now()->gte($autoCheckoutTime)) {
                    // Set check-out time to auto-checkout time
                    $attendance->update([
                        'check_out_time' => $autoCheckoutTime->format('H:i:s')
                    ]);
                    
                    $autoCheckoutCount++;
                    
                    Log::info('Auto checkout executed', [
                        'member_id' => $attendance->member_id,
                        'member_name' => $attendance->member->nama_lengkap,
                        'tanggal' => $today,
                        'check_in_time' => $attendance->check_in_time,
                        'auto_checkout_time' => $autoCheckoutTime->format('H:i:s'),
                    ]);
                }
            }
            
            Log::info('Auto checkout completed', [
                'total_processed' => $autoCheckoutCount,
                'config_checkout_time' => $config->reminder_check_out_time,
                'auto_checkout_threshold' => $autoCheckoutTime->format('H:i:s'),
            ]);

            $this->info("Auto checkout completed. {$autoCheckoutCount} members auto-checked-out.");
            return self::SUCCESS;

        } catch (\Exception $e) {
            Log::error('Auto checkout error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
