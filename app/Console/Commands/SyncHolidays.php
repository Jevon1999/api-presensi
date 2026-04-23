<?php

namespace App\Console\Commands;

use App\Services\HolidaySyncService;
use Illuminate\Console\Command;

class SyncHolidays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holidays:sync {year? : Tahun yang akan disync, default tahun berjalan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi hari libur nasional dari libur.deno.dev ke database';

    /**
     * Execute the console command.
     */
    public function handle(HolidaySyncService $syncService): int
    {
        $year = $this->argument('year')
            ? (int) $this->argument('year')
            : (int) now()->format('Y');

        $this->info("🗓 Sinkronisasi hari libur tahun {$year} dari libur.deno.dev ...");

        try {
            $result = $syncService->syncYear($year);

            $this->info("✅ Selesai! {$result['synced']} hari libur tersimpan.");
            if ($result['errors'] > 0) {
                $this->warn("⚠️  {$result['errors']} entri gagal (lihat log).");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Gagal: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
