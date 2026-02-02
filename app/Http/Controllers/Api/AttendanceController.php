<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\OfficeLocation;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Display attendance list with filters
     */
    public function index(Request $request)
    {
        $query = Attendance::with('member.office');

        // filter by tanggal
        if ($request->has('date')) {
            $query->whereDate('tanggal', $request->date);
        } else {
            // Default: hari ini
            $query->whereDate('tanggal', today());
        }

        // filter by member
        if ($request->has('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        // filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // filter by office
        if ($request->has('office_id')) {
            $query->whereHas('member', function($q) use ($request) {
                $q->where('office_id', $request->office_id);
            });
        }

        $attendances = $query->latest('tanggal')
            ->latest('check_in_time')
            ->paginate(50);

        return response()->json($attendances);
    }

    /**
     * show specific attendance
     */
    public function show(Attendance $attendance)
    {
        $attendance->load(['member.office', 'permissions', 'resetLogs']);

        return response()->json([
            'data' => $attendance
        ]);
    }

    /**
     * check-in endpoint (dari bot WA atau manual)
     */
    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'no_hp' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        // 1. cari member by no hp
        $member = Member::where('no_hp', $validated['no_hp'])
            ->where('status_aktif', true)
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'Member tidak ditemukan atau tidak aktif'
            ], 404);
        }

        // 2. cek apakah sudah check-in hari ini
        $today = now()->format('Y-m-d');
        $existingAttendance = Attendance::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->first();

        if ($existingAttendance) {
            if ($existingAttendance->check_in_time) {
                return response()->json([
                    'message' => 'Kamu sudah check-in hari ini',
                    'data' => [
                        'check_in_time' => $existingAttendance->check_in_time->format('H:i'),
                        'status' => $existingAttendance->status
                    ]
                ], 400);
            }
        }

        // 3. validasi geofencing
        $validLocation = $this->validateGeofencing(
            $validated['latitude'],
            $validated['longitude'],
            $member->office_id
        );

        if (!$validLocation) {
            return response()->json([
                'message' => 'Lokasi kamu di luar jangkauan kantor. Pastikan kamu berada di area kantor.',
                'debug' => [
                    'your_location' => [
                        'lat' => $validated['latitude'],
                        'lng' => $validated['longitude']
                    ]
                ]
            ], 403);
        }

        // 4. buat atau update attendance
        $checkInTime = now()->format('H:i:s');
        
        if ($existingAttendance) {
            $existingAttendance->update([
                'check_in_time' => $checkInTime,
                'status' => 'hadir'
            ]);
            $attendance = $existingAttendance;
        } else {
            $attendance = Attendance::create([
                'member_id' => $member->id,
                'tanggal' => $today,
                'check_in_time' => $checkInTime,
                'status' => 'hadir',
            ]);
        }

        return response()->json([
            'message' => 'Check-in berhasil!',
            'data' => [
                'member' => [
                    'id' => $member->id,
                    'nama' => $member->nama_lengkap,
                    'office' => $member->office->name
                ],
                'attendance' => [
                    'tanggal' => $attendance->tanggal->format('d/m/Y'),
                    'check_in_time' => Carbon::parse($attendance->check_in_time)->format('H:i'),
                    'status' => $attendance->status
                ]
            ]
        ], 201);
    }

    /**
     * check-out endpoint
     */
    public function checkOut(Request $request)
    {
        $validated = $request->validate([
            'no_hp' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        // 1. cari member
        $member = Member::where('no_hp', $validated['no_hp'])
            ->where('status_aktif', true)
            ->first();

        if (!$member) {
            return response()->json([
                'message' => 'Member tidak ditemukan atau tidak aktif'
            ], 404);
        }

        // 2. cari attendance hari ini
        $today = now()->format('Y-m-d');
        $attendance = Attendance::where('member_id', $member->id)
            ->where('tanggal', $today)
            ->first();

        if (!$attendance) {
            return response()->json([
                'message' => 'Kamu belum check-in hari ini'
            ], 400);
        }

        if (!$attendance->check_in_time) {
            return response()->json([
                'message' => 'Kamu belum check-in hari ini'
            ], 400);
        }

        if ($attendance->check_out_time) {
            return response()->json([
                'message' => 'Kamu sudah check-out hari ini',
                'data' => [
                    'check_out_time' => $attendance->check_out_time->format('H:i')
                ]
            ], 400);
        }

        // 3. validasi geofencing
        $validLocation = $this->validateGeofencing(
            $validated['latitude'],
            $validated['longitude'],
            $member->office_id
        );

        if (!$validLocation) {
            return response()->json([
                'message' => 'Lokasi kamu di luar jangkauan kantor. Pastikan kamu berada di area kantor.'
            ], 403);
        }

        // 4. update attendance dengan waktu check-out
        $checkOutTime = now()->format('H:i:s');
        $attendance->update([
            'check_out_time' => $checkOutTime
        ]);

        // hitung jam kerja
        $checkIn = Carbon::parse($attendance->check_in_time);
        $checkOut = Carbon::parse($checkOutTime);
        $workingHours = $checkIn->diffInHours($checkOut);
        $workingMinutes = $checkIn->diffInMinutes($checkOut) % 60;

        return response()->json([
            'message' => 'Check-out berhasil!',
            'data' => [
                'member' => [
                    'id' => $member->id,
                    'nama' => $member->nama_lengkap
                ],
                'attendance' => [
                    'tanggal' => $attendance->tanggal->format('d/m/Y'),
                    'check_in_time' => $checkIn->format('H:i'),
                    'check_out_time' => $checkOut->format('H:i'),
                    'working_hours' => "{$workingHours} jam {$workingMinutes} menit"
                ]
            ]
        ]);
    }

    /**
     * Admin: Reset attendance (create log)
     */
    public function reset(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'status' => 'required|in:hadir,izin,sakit,alpha',
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i',
            'reason' => 'required|string|min:10'
        ]);

        // bikin reset log
        $attendance->resetLogs()->create([
            'reset_by' => auth()->id(),
            'old_status' => $attendance->status,
            'new_status' => $validated['status'],
            'old_check_in' => $attendance->check_in_time,
            'new_check_in' => $validated['check_in_time'] ?? null,
            'old_check_out' => $attendance->check_out_time,
            'new_check_out' => $validated['check_out_time'] ?? null,
            'reason' => $validated['reason']
        ]);

        // update attendance
        $attendance->update([
            'status' => $validated['status'],
            'check_in_time' => $validated['check_in_time'] ?? $attendance->check_in_time,
            'check_out_time' => $validated['check_out_time'] ?? $attendance->check_out_time,
        ]);

        return response()->json([
            'message' => 'Attendance berhasil direset',
            'data' => $attendance->fresh(['resetLogs'])
        ]);
    }

    /**
     * Validate lokasi office geofence
     */
    private function validateGeofencing($latitude, $longitude, $officeId)
    {
        $locations = OfficeLocation::where('office_id', $officeId)
            ->where('is_active', true)
            ->get();

        if ($locations->isEmpty()) {
            // Tidak ada lokasi yang diset, izinkan check-in (untuk development)
            return true;
        }

        foreach ($locations as $location) {
            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
                $location->latitude,
                $location->longitude
            );

            // Cek apakah dalam radius
            if ($distance <= $location->radius_meters) {
                return true;
            }
        }

        return false;
    }

    /**
     * kalkulasi jarak antara dua koordinat (haversine formula)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // dalam meter

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        $distance = $earthRadius * $c;

        return $distance; // dalam meter
    }

    /**
     * Get laporan/statistik kehadiran
     */
    public function report(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'office_id' => 'nullable|exists:offices,id',
            'member_id' => 'nullable|exists:members,id'
        ]);

        $query = Attendance::with('member.office')
            ->whereBetween('tanggal', [$validated['start_date'], $validated['end_date']]);

        if (isset($validated['member_id'])) {
            $query->where('member_id', $validated['member_id']);
        }

        if (isset($validated['office_id'])) {
            $query->whereHas('member', function($q) use ($validated) {
                $q->where('office_id', $validated['office_id']);
            });
        }

        $attendances = $query->get();

        // hitung statistik
        $stats = [
            'total_days' => $attendances->count(),
            'hadir' => $attendances->where('status', 'hadir')->count(),
            'izin' => $attendances->where('status', 'izin')->count(),
            'sakit' => $attendances->where('status', 'sakit')->count(),
            'alpha' => $attendances->where('status', 'alpha')->count(),
        ];

        return response()->json([
            'period' => [
                'start' => $validated['start_date'],
                'end' => $validated['end_date']
            ],
            'statistics' => $stats,
            'attendances' => $attendances
        ]);
    }
}