<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\BotConfig;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Get bot config for scheduling times
        $config = BotConfig::where('is_active', true)->first();
        
        if ($config && $config->reminder_enabled) {
            $timezone = $config->timezone ?: 'Asia/Jakarta';
            
            // Schedule check-in reminder
            if ($config->reminder_check_in_time) {
                $checkInTime = \Carbon\Carbon::parse($config->reminder_check_in_time)->format('H:i');
                $schedule->command('bot:send-checkin-reminder')
                    ->dailyAt($checkInTime)
                    ->timezone($timezone)
                    ->withoutOverlapping()
                    ->runInBackground();
            }
            
            // Schedule check-out reminder
            if ($config->reminder_check_out_time) {
                $checkOutTime = \Carbon\Carbon::parse($config->reminder_check_out_time)->format('H:i');
                $schedule->command('bot:send-checkout-reminder')
                    ->dailyAt($checkOutTime)
                    ->timezone($timezone)
                    ->withoutOverlapping()
                    ->runInBackground();
            }
            
            // Schedule late notification (30 minutes after late threshold)
            if ($config->check_in_late_threshold) {
                $lateTime = \Carbon\Carbon::parse($config->check_in_late_threshold)->addMinutes(30)->format('H:i');
                $schedule->command('bot:send-late-notification')
                    ->dailyAt($lateTime)
                    ->timezone($timezone)
                    ->withoutOverlapping()
                    ->runInBackground();
            }
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
