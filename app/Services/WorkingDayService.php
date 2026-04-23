<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\BotConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WorkingDayService
{
    /**
     * Hari-hari kerja default jika belum dikonfigurasi (Senin–Jumat).
     * Format: ISO-8601 weekday number (1=Mon, 2=Tue, ..., 7=Sun).
     */
    private const DEFAULT_WORKING_DAYS = ['1', '2', '3', '4', '5'];

    /**
     * Ambil daftar hari kerja dari bot_config.
     */
    private function getWorkingDays(): array
    {
        $config = BotConfig::config();
        $days = $config->working_days;

        // Fallback ke default jika null atau kosong
        if (empty($days)) {
            return self::DEFAULT_WORKING_DAYS;
        }

        return array_map('strval', (array) $days);
    }

    /**
     * Cek apakah $date adalah hari kerja (bukan weekend dan bukan tanggal merah).
     *
     * @param Carbon|null $date Tanggal yang dicek. Default: today.
     * @return bool
     */
    public function isWorkingDay(?Carbon $date = null): bool
    {
        $date = $date ?? Carbon::today();

        // Cek 1: Apakah hari ini termasuk hari kerja (konfigurasi weekday)?
        $dayOfWeek = (string) $date->isoWeekday(); // 1=Mon ... 7=Sun
        $workingDays = $this->getWorkingDays();

        if (!in_array($dayOfWeek, $workingDays, true)) {
            return false;
        }

        // Cek 2: Apakah tanggal merah (holiday)?
        if (Holiday::isHoliday($date)) {
            return false;
        }

        return true;
    }

    /**
     * Ambil status lengkap hari ini beserta alasan jika bukan hari kerja.
     *
     * @param Carbon|null $date
     * @return array{is_working: bool, reason: string|null, day_name: string}
     */
    public function getDayStatus(?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();

        $dayOfWeek = (string) $date->isoWeekday();
        $workingDays = $this->getWorkingDays();

        // Nama hari Indonesia
        $dayNames = [
            '1' => 'Senin', '2' => 'Selasa', '3' => 'Rabu',
            '4' => 'Kamis', '5' => 'Jumat', '6' => 'Sabtu', '7' => 'Minggu',
        ];
        $dayName = $dayNames[$dayOfWeek] ?? $date->format('l');

        if (!in_array($dayOfWeek, $workingDays, true)) {
            return [
                'is_working' => false,
                'reason'     => 'weekend',
                'day_name'   => $dayName,
                'detail'     => "Hari {$dayName} bukan hari kerja.",
            ];
        }

        $holidayName = Holiday::getHolidayName($date);
        if ($holidayName !== null) {
            return [
                'is_working' => false,
                'reason'     => 'holiday',
                'day_name'   => $dayName,
                'detail'     => $holidayName,
            ];
        }

        return [
            'is_working' => true,
            'reason'     => null,
            'day_name'   => $dayName,
            'detail'     => null,
        ];
    }

    /**
     * Helper untuk dipakai di Kernel.php scheduler.
     * Mengembalikan true jika hari ini adalah hari kerja.
     */
    public function isTodayWorkingDay(): bool
    {
        return $this->isWorkingDay(Carbon::today());
    }
}
