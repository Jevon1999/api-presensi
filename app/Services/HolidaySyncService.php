<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HolidaySyncService
{
    private const API_BASE = 'https://libur.deno.dev/api';

    /**
     * Fetch hari libur dari libur.deno.dev untuk tahun tertentu,
     * lalu upsert ke tabel holidays.
     *
     * @param int $year Tahun yang akan di-sync
     * @return array{synced: int, year: int, errors: int}
     */
    public function syncYear(int $year): array
    {
        $url = self::API_BASE . '?year=' . $year;

        try {
            $response = Http::timeout(15)->get($url);

            if (!$response->successful()) {
                Log::error("HolidaySyncService: API error", [
                    'url'    => $url,
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);
                throw new \RuntimeException("API libur.deno.dev merespons {$response->status()}");
            }

            $data = $response->json();

            if (!is_array($data) || empty($data)) {
                throw new \RuntimeException("Response kosong atau tidak valid untuk tahun {$year}");
            }

            $synced = 0;
            $errors = 0;

            foreach ($data as $item) {
                // Validasi minimal: harus punya date & name
                if (empty($item['date']) || empty($item['name'])) {
                    $errors++;
                    continue;
                }

                try {
                    Holiday::updateOrCreate(
                        ['tanggal' => $item['date']],
                        [
                            'nama'   => $item['name'],
                            'tahun'  => $year,
                            'source' => 'libur.deno.dev',
                        ]
                    );
                    $synced++;
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning("HolidaySyncService: Gagal upsert {$item['date']}: " . $e->getMessage());
                }
            }

            Log::info("HolidaySyncService: Sync selesai", [
                'year'   => $year,
                'synced' => $synced,
                'errors' => $errors,
            ]);

            return ['synced' => $synced, 'year' => $year, 'errors' => $errors];

        } catch (\Throwable $e) {
            Log::error("HolidaySyncService: Exception saat sync tahun {$year}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync hari libur tahun berjalan.
     */
    public function syncCurrentYear(): array
    {
        return $this->syncYear((int) now()->format('Y'));
    }

    /**
     * Sync hari libur tahun depan.
     */
    public function syncNextYear(): array
    {
        return $this->syncYear((int) now()->addYear()->format('Y'));
    }
}
