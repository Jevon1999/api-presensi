<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Attendance;
use App\Models\Progress;
use Carbon\Carbon;

class MemberDashboardController extends Controller
{
    /**
     * Get the authenticated member's dashboard data.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $member = Member::where('user_id', $user->id)
            ->where('status', 'approved')
            ->with('office:id,name')
            ->first();

        if (!$member) {
            return response()->json(['message' => 'Member tidak ditemukan.'], 404);
        }

        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();

        // Today's attendance
        $todayAttendance = Attendance::where('member_id', $member->id)
            ->whereDate('tanggal', $today)
            ->first();

        // This month's attendance stats
        $monthAttendances = Attendance::where('member_id', $member->id)
            ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
            ->get();

        $totalHadir = $monthAttendances->count();
        $totalLate = $monthAttendances->where('is_late', true)->count();

        // Working days in current month (Mon-Fri up to today)
        $workingDays = 0;
        $d = $startOfMonth->copy();
        $limit = $today->copy()->min($endOfMonth);
        while ($d->lte($limit)) {
            if ($d->isWeekday()) $workingDays++;
            $d->addDay();
        }

        $totalAbsen = max(0, $workingDays - $totalHadir);

        // Recent 7 attendance records
        $recentAttendances = Attendance::where('member_id', $member->id)
            ->orderByDesc('tanggal')
            ->limit(7)
            ->get();

        return response()->json([
            'member' => $member,
            'today' => $todayAttendance,
            'stats' => [
                'total_hadir' => $totalHadir,
                'total_absen' => $totalAbsen,
                'total_terlambat' => $totalLate,
                'working_days' => $workingDays,
            ],
            'recent_attendances' => $recentAttendances,
        ]);
    }

    /**
     * Get the authenticated member's progress records.
     */
    public function progress(Request $request)
    {
        $user = $request->user();
        $member = Member::where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();

        if (!$member) {
            return response()->json(['message' => 'Member tidak ditemukan.'], 404);
        }

        $progresses = Progress::where('member_id', $member->id)
            ->orderByDesc('tanggal')
            ->paginate(15);

        return response()->json($progresses);
    }

    /**
     * Get the authenticated member's attendance report for printing.
     */
    public function report(Request $request)
    {
        $user = $request->user();
        $member = Member::where('user_id', $user->id)
            ->where('status', 'approved')
            ->with('office:id,name')
            ->first();

        if (!$member) {
            return response()->json(['message' => 'Member tidak ditemukan.'], 404);
        }

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)
            : Carbon::parse($member->tanggal_mulai_magang);
        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)
            : Carbon::today();

        $attendances = Attendance::where('member_id', $member->id)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->orderBy('tanggal')
            ->get();

        // Count stats
        $totalHadir = $attendances->count();
        $totalLate = $attendances->where('is_late', true)->count();

        // Working days in range
        $workingDays = 0;
        $d = $startDate->copy();
        while ($d->lte($endDate)) {
            if ($d->isWeekday()) $workingDays++;
            $d->addDay();
        }

        return response()->json([
            'member' => $member,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'stats' => [
                'working_days' => $workingDays,
                'total_hadir' => $totalHadir,
                'total_absen' => max(0, $workingDays - $totalHadir),
                'total_terlambat' => $totalLate,
                'persentase' => $workingDays > 0 ? round(($totalHadir / $workingDays) * 100, 1) : 0,
            ],
            'attendances' => $attendances,
        ]);
    }
}
