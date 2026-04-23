<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Services\HolidaySyncService;
use App\Services\WorkingDayService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function __construct(
        private HolidaySyncService $syncService,
        private WorkingDayService  $workingDayService,
    ) {}

    /**
     * GET /api/holidays?year=2026
     * Ambil daftar hari libur dari DB (sudah di-cache).
     */
    public function index(Request $request)
    {
        $year = (int) ($request->query('year', now()->year));

        $holidays = Holiday::where('tahun', $year)
            ->orderBy('tanggal')
            ->get()
            ->map(fn($h) => [
                'id'     => $h->id,
                'tanggal' => $h->tanggal->format('Y-m-d'),
                'nama'   => $h->nama,
                'tahun'  => $h->tahun,
                'source' => $h->source,
            ]);

        return response()->json([
            'year'     => $year,
            'total'    => $holidays->count(),
            'data'     => $holidays,
        ]);
    }

    /**
     * POST /api/holidays/sync?year=2026
     * Fetch & simpan hari libur dari libur.deno.dev.
     */
    public function sync(Request $request)
    {
        $year = (int) ($request->query('year', now()->year));

        try {
            $result = $this->syncService->syncYear($year);

            return response()->json([
                'message' => "Sync berhasil: {$result['synced']} hari libur disimpan untuk tahun {$year}.",
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal sync dari libur.deno.dev: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * POST /api/holidays
     * Tambah hari libur manual.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => 'required|date|unique:holidays,tanggal',
            'nama'    => 'required|string|max:255',
        ]);

        $holiday = Holiday::create([
            'tanggal' => $validated['tanggal'],
            'nama'    => $validated['nama'],
            'tahun'   => (int) Carbon::parse($validated['tanggal'])->format('Y'),
            'source'  => 'manual',
        ]);

        return response()->json([
            'message' => 'Hari libur berhasil ditambahkan.',
            'data'    => $holiday,
        ], 201);
    }

    /**
     * DELETE /api/holidays/{holiday}
     * Hapus hari libur.
     */
    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return response()->json([
            'message' => "Hari libur '{$holiday->nama}' ({$holiday->tanggal->format('d/m/Y')}) berhasil dihapus.",
        ]);
    }

    /**
     * GET /api/working-day/status?date=2026-08-17
     * Cek status hari kerja (untuk debug & frontend info).
     */
    public function dayStatus(Request $request)
    {
        $dateParam = $request->query('date');
        $date = $dateParam ? Carbon::parse($dateParam) : Carbon::today();

        $status = $this->workingDayService->getDayStatus($date);

        return response()->json(array_merge($status, [
            'date' => $date->toDateString(),
        ]));
    }
}
