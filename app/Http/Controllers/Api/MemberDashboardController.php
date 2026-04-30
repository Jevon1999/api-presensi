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
     * Also returns today's attendance for lock-state determination.
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

        // Today's attendance for lock-state
        $todayAttendance = Attendance::where('member_id', $member->id)
            ->whereDate('tanggal', Carbon::today())
            ->first();

        // Merge today_attendance into paginated response
        $response = $progresses->toArray();
        $response['today_attendance'] = $todayAttendance;
        $response['member'] = $member;

        return response()->json($response);
    }

    /**
     * Helper: get authenticated member or return 404.
     */
    private function getAuthMember(Request $request)
    {
        $user = $request->user();
        return Member::where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();
    }

    /**
     * Helper: check if member can manage progress (checked-in, not checked-out).
     * Returns [bool $allowed, string|null $reason]
     */
    private function checkProgressLock($member)
    {
        $attendance = Attendance::where('member_id', $member->id)
            ->whereDate('tanggal', Carbon::today())
            ->first();

        if (!$attendance || !$attendance->check_in_time) {
            return [false, 'Kamu belum check-in hari ini. Silakan check-in terlebih dahulu.'];
        }

        if ($attendance->check_out_time) {
            return [false, 'Kamu sudah check-out hari ini. Progress tidak bisa diubah setelah checkout.'];
        }

        return [true, null];
    }

    /**
     * Store a new progress record for the authenticated member.
     */
    public function storeProgress(Request $request)
    {
        $member = $this->getAuthMember($request);
        if (!$member) {
            return response()->json(['message' => 'Member tidak ditemukan.'], 404);
        }

        [$allowed, $reason] = $this->checkProgressLock($member);
        if (!$allowed) {
            return response()->json(['message' => $reason], 403);
        }

        $validated = $request->validate([
            'tipe'        => 'required|in:hadir,sakit,izin',
            'description' => [
                'required',
                'string',
                'min:3',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->tipe === 'sakit' && trim($value) === 'Pulang') {
                        $fail('Keterangan sakit harus diisi dengan alasan yang sesuai.');
                    }
                },
            ],
        ]);

        $today = Carbon::today()->toDateString();

        // Check if progress already exists for today
        $exists = Progress::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Laporan untuk hari ini sudah ada. Silakan edit laporan yang sudah ada.'], 422);
        }

        $progress = Progress::create([
            'member_id'   => $member->id,
            'tanggal'     => $today,
            'tipe'        => $validated['tipe'],
            'description' => $validated['description'],
        ]);

        return response()->json([
            'message' => 'Laporan berhasil disimpan.',
            'data'    => $progress,
        ], 201);
    }

    /**
     * Update the authenticated member's progress record.
     */
    public function updateProgress(Request $request, $id)
    {
        $member = $this->getAuthMember($request);
        if (!$member) {
            return response()->json(['message' => 'Member tidak ditemukan.'], 404);
        }

        $progress = Progress::where('id', $id)
            ->where('member_id', $member->id)
            ->first();

        if (!$progress) {
            return response()->json(['message' => 'Progress tidak ditemukan.'], 404);
        }

        // Members can only edit today's progress
        if (!$progress->tanggal->isToday()) {
            return response()->json(['message' => 'Hanya bisa mengedit progress hari ini.'], 403);
        }

        [$allowed, $reason] = $this->checkProgressLock($member);
        if (!$allowed) {
            return response()->json(['message' => $reason], 403);
        }

        $validated = $request->validate([
            'tipe'        => 'sometimes|in:hadir,sakit,izin',
            'description' => 'required|string|min:3',
        ]);

        $progress->update($validated);

        return response()->json([
            'message' => 'Laporan berhasil diupdate.',
            'data'    => $progress,
        ]);
    }

    /**
     * Delete the authenticated member's progress record.
     */
    public function destroyProgress(Request $request, $id)
    {
        $member = $this->getAuthMember($request);
        if (!$member) {
            return response()->json(['message' => 'Member tidak ditemukan.'], 404);
        }

        $progress = Progress::where('id', $id)
            ->where('member_id', $member->id)
            ->first();

        if (!$progress) {
            return response()->json(['message' => 'Progress tidak ditemukan.'], 404);
        }

        // Members can only delete today's progress
        if (!$progress->tanggal->isToday()) {
            return response()->json(['message' => 'Hanya bisa menghapus progress hari ini.'], 403);
        }

        [$allowed, $reason] = $this->checkProgressLock($member);
        if (!$allowed) {
            return response()->json(['message' => $reason], 403);
        }

        $progress->delete();

        return response()->json(['message' => 'Progress berhasil dihapus.']);
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
