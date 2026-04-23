<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\BotConfig;
use App\Services\WorkingDayService;

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

            // ── Helper: cek apakah hari ini hari kerja ──
            // Dibungkus dalam closure agar dievaluasi saat command jalan, bukan saat boot
            $isWorkingDay = fn () => app(WorkingDayService::class)->isTodayWorkingDay();

            // Schedule check-in reminder — HANYA di hari kerja
            if ($config->reminder_check_in_time) {
                $checkInTime = \Carbon\Carbon::parse($config->reminder_check_in_time)->format('H:i');
                $schedule->command('bot:send-checkin-reminder')
                    ->dailyAt($checkInTime)
                    ->timezone($timezone)
                    ->withoutOverlapping()
                    ->runInBackground()
                    ->when($isWorkingDay);
            }

            // Schedule check-out reminder — HANYA di hari kerja
            if ($config->reminder_check_out_time) {
                $checkOutTime = \Carbon\Carbon::parse($config->reminder_check_out_time)->format('H:i');
                $schedule->command('bot:send-checkout-reminder')
                    ->dailyAt($checkOutTime)
                    ->timezone($timezone)
                    ->withoutOverlapping()
                    ->runInBackground()
                    ->when($isWorkingDay);
            }

            // Schedule late notification (30 menit setelah threshold) — HANYA di hari kerja
            if ($config->check_in_late_threshold) {
                $lateTime = \Carbon\Carbon::parse($config->check_in_late_threshold)->addMinutes(30)->format('H:i');
                $schedule->command('bot:send-late-notification')
                    ->dailyAt($lateTime)
                    ->timezone($timezone)
                    ->withoutOverlapping()
                    ->runInBackground()
                    ->when($isWorkingDay);
            }

            // Schedule auto-checkout (3 jam setelah jam checkout) — HANYA di hari kerja
            if ($config->reminder_check_out_time) {
                $autoCheckoutTime = \Carbon\Carbon::parse($config->reminder_check_out_time)->addHours(3)->format('H:i');
                $schedule->command('bot:auto-checkout')
                    ->dailyAt($autoCheckoutTime)
                    ->timezone($timezone)
                    ->withoutOverlapping()
                    ->runInBackground()
                    ->when($isWorkingDay);
            }

            // Schedule alpha/bolos marking — HANYA di hari kerja
            if ($config->reminder_check_out_time) {
                $checkoutTime = \Carbon\Carbon::parse($config->reminder_check_out_time)->format('H:i');
                $schedule->command('bot:mark-alpha-status')
                    ->dailyAt($checkoutTime)
                    ->timezone($timezone)
                    ->withoutOverlapping()
                    ->runInBackground()
                    ->when($isWorkingDay);
            }
        }

        // Deactivate expired members (run daily just after midnight) — selalu jalan
        $schedule->call(function () {
            \App\Models\Member::where('status_aktif', true)
                ->whereNotNull('tanggal_selesai_magang')
                ->whereDate('tanggal_selesai_magang', '<', now()->toDateString())
                ->update(['status_aktif' => false]);
        })->dailyAt('00:01')->timezone('Asia/Jakarta');

        // Auto-sync hari libur tahun depan setiap 1 Desember jam 01:00
        $schedule->command('holidays:sync', [now()->addYear()->year])
            ->yearlyOn(12, 1, '01:00')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping();
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
