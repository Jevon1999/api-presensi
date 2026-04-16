<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'office_id'  => 'nullable|exists:offices,id',
        ]);

        $startDate = $validated['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate   = $validated['end_date']   ?? now()->toDateString();
        $officeId  = $validated['office_id']  ?? null;

        // ─────────────────────────────────────────────────────────
        // Helper: base sub-query — attendance IDs yang valid
        // (dalam periode & member yang aktif/sesuai office)
        // ─────────────────────────────────────────────────────────
        $validMemberIds = DB::table('members')
            ->whereNotNull('user_id')
            ->whereNull('deleted_at')
            ->when($officeId, fn($q) => $q->where('office_id', $officeId))
            ->pluck('id');

        // Shortcut untuk query dasar attendance
        $base = fn() => DB::table('attendances')
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->whereIn('member_id', $validMemberIds)
            ->whereNull('deleted_at');

        // ─────────────────────────────────────────────────────────
        // 1. SUMMARY
        // ─────────────────────────────────────────────────────────
        $summaryRaw = $base()
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(status = "hadir") as hadir'),
                DB::raw('SUM(status = "izin")  as izin'),
                DB::raw('SUM(status = "sakit") as sakit'),
                DB::raw('SUM(status = "alpha") as alpha'),
                DB::raw('SUM(work_type = "wfo") as wfo'),
                DB::raw('SUM(work_type = "wfa") as wfa'),
                DB::raw('SUM(is_late = 1)       as terlambat'),
            ])
            ->first();

        $summary = [
            'total'     => (int) ($summaryRaw->total     ?? 0),
            'hadir'     => (int) ($summaryRaw->hadir     ?? 0),
            'izin'      => (int) ($summaryRaw->izin      ?? 0),
            'sakit'     => (int) ($summaryRaw->sakit     ?? 0),
            'alpha'     => (int) ($summaryRaw->alpha     ?? 0),
            'wfo'       => (int) ($summaryRaw->wfo       ?? 0),
            'wfa'       => (int) ($summaryRaw->wfa       ?? 0),
            'terlambat' => (int) ($summaryRaw->terlambat ?? 0),
        ];

        // ─────────────────────────────────────────────────────────
        // 2. TREND HARIAN
        // ─────────────────────────────────────────────────────────
        $trendRaw = $base()
            ->select([
                DB::raw('DATE(tanggal) as date'),
                DB::raw('SUM(status = "hadir") as hadir'),
                DB::raw('SUM(status = "alpha") as alpha'),
                DB::raw('SUM(status = "izin")  as izin'),
                DB::raw('SUM(status = "sakit") as sakit'),
            ])
            ->groupBy(DB::raw('DATE(tanggal)'))
            ->orderBy('date')
            ->get();

        $trendDaily = $trendRaw->map(fn($r) => [
            'date'  => $r->date,
            'hadir' => (int) $r->hadir,
            'alpha' => (int) $r->alpha,
            'izin'  => (int) $r->izin,
            'sakit' => (int) $r->sakit,
        ]);

        // ─────────────────────────────────────────────────────────
        // 3. BY SCHOOL — join bersih dengan members
        // ─────────────────────────────────────────────────────────
        $bySchoolRaw = $base()
            ->join('members', 'members.id', '=', 'attendances.member_id')
            ->select([
                DB::raw('TRIM(LOWER(members.asal_sekolah)) as sekolah'),
                DB::raw('members.asal_sekolah as sekolah_asli'),
                DB::raw('COUNT(DISTINCT attendances.member_id) as total_siswa'),
                DB::raw('COUNT(*) as total_absensi'),
                DB::raw('SUM(attendances.status = "hadir") as hadir'),
                DB::raw('SUM(attendances.status = "alpha") as alpha'),
                DB::raw('SUM(attendances.status = "izin")  as izin'),
                DB::raw('SUM(attendances.status = "sakit") as sakit'),
                DB::raw('SUM(attendances.is_late = 1)      as terlambat'),
                DB::raw('SUM(attendances.work_type = "wfo") as wfo'),
                DB::raw('SUM(attendances.work_type = "wfa") as wfa'),
            ])
            ->groupBy(DB::raw('TRIM(LOWER(members.asal_sekolah))'), 'members.asal_sekolah')
            ->orderByDesc('hadir')
            ->get();

        $bySchool = $bySchoolRaw->map(function ($r) {
            $total = (int) $r->total_absensi;
            return [
                'sekolah'       => ucwords($r->sekolah),
                'sekolah_asli'  => $r->sekolah_asli,
                'total_siswa'   => (int) $r->total_siswa,
                'total_absensi' => $total,
                'hadir'         => (int) $r->hadir,
                'hadir_pct'     => $total > 0 ? round(($r->hadir / $total) * 100, 1) : 0,
                'alpha'         => (int) $r->alpha,
                'alpha_pct'     => $total > 0 ? round(($r->alpha / $total) * 100, 1) : 0,
                'izin'          => (int) $r->izin,
                'sakit'         => (int) $r->sakit,
                'terlambat'     => (int) $r->terlambat,
                'terlambat_pct' => $total > 0 ? round(($r->terlambat / $total) * 100, 1) : 0,
                'wfo'           => (int) $r->wfo,
                'wfa'           => (int) $r->wfa,
            ];
        });

        // ─────────────────────────────────────────────────────────
        // 4. LATE RANKING — top 10 paling sering terlambat
        // ─────────────────────────────────────────────────────────
        $lateRankingRaw = $base()
            ->join('members', 'members.id', '=', 'attendances.member_id')
            ->select([
                'attendances.member_id',
                DB::raw('COUNT(*) as count'),
                DB::raw('members.nama_lengkap as nama'),
                DB::raw('TRIM(LOWER(members.asal_sekolah)) as sekolah'),
                DB::raw('members.jurusan as jurusan'),
            ])
            ->where('attendances.is_late', true)
            ->groupBy('attendances.member_id', 'members.nama_lengkap', 'members.asal_sekolah', 'members.jurusan')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $lateRanking = $lateRankingRaw->map(fn($r) => [
            'member_id' => $r->member_id,
            'nama'      => $r->nama,
            'sekolah'   => ucwords($r->sekolah),
            'jurusan'   => $r->jurusan,
            'count'     => (int) $r->count,
        ]);

        // ─────────────────────────────────────────────────────────
        // 5. DISTRIBUSI JAM CHECK-IN
        // ─────────────────────────────────────────────────────────
        $checkInRaw = $base()
            ->select([
                DB::raw('HOUR(check_in_time) as jam'),
                DB::raw('COUNT(*) as total'),
            ])
            ->whereNotNull('check_in_time')
            ->where('status', 'hadir')
            ->groupBy(DB::raw('HOUR(check_in_time)'))
            ->orderBy('jam')
            ->get();

        $checkInDist = [];
        for ($h = 6; $h <= 18; $h++) {
            $row = $checkInRaw->firstWhere('jam', $h);
            $checkInDist[sprintf('%02d:00', $h)] = $row ? (int) $row->total : 0;
        }

        return response()->json([
            'period'               => ['start' => $startDate, 'end' => $endDate],
            'summary'              => $summary,
            'trend_daily'          => $trendDaily->values(),
            'by_school'            => $bySchool->values(),
            'late_ranking'         => $lateRanking->values(),
            'checkin_distribution' => $checkInDist,
        ]);
    }
}
