<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Office;

class MemberApplicationController extends Controller
{
    /**
     * User submits a member application.
     */
    public function apply(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json(['message' => 'Hanya user biasa yang bisa mengajukan pendaftaran.'], 403);
        }

        // Cek jika sudah ada pengajuan pending atau sudah approved
        $existing = Member::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            $msg = $existing->status === 'pending'
                ? 'Anda sudah memiliki pengajuan yang sedang diproses.'
                : 'Anda sudah terdaftar sebagai member.';
            return response()->json(['message' => $msg], 422);
        }

        // Cek apakah ada pengajuan sebelumnya yang DITOLAK (rejected)
        // Jika ada, update record lama ke pending (bukan buat record baru)
        $rejected = Member::where('user_id', $user->id)
            ->where('status', 'rejected')
            ->first();

        // Validasi — jika re-apply, abaikan no_hp dari record rejected sendiri
        $noHpRule = $rejected
            ? ['required', 'string', 'max:15', \Illuminate\Validation\Rule::unique('members', 'no_hp')->ignore($rejected->id)]
            : ['required', 'string', 'max:15', 'unique:members,no_hp'];

        $request->validate([
            'no_hp'                  => $noHpRule,
            'office_id'              => 'required|exists:offices,id',
            'jenis_kelamin'          => 'required|in:L,P',
            'asal_sekolah'           => 'required|string|max:255',
            'jurusan'                => 'required|string|max:255',
            'tanggal_mulai_magang'   => 'required|date',
            'tanggal_selesai_magang' => 'nullable|date|after_or_equal:tanggal_mulai_magang',
        ]);

        // Normalisasi nomor HP ke format +62
        $noHp = preg_replace('/[^\d+]/', '', $request->no_hp);
        if (preg_match('/^08/', $noHp)) {
            $noHp = '+62' . substr($noHp, 1);
        } elseif (preg_match('/^628/', $noHp)) {
            $noHp = '+' . $noHp;
        } elseif (preg_match('/^8\d{8,}/', $noHp)) {
            $noHp = '+62' . $noHp;
        }

        if ($rejected) {
            // UPDATE record rejected menjadi pending kembali
            $rejected->update([
                'no_hp'                  => $noHp,
                'office_id'              => $request->office_id,
                'nama_lengkap'           => $user->name,
                'jenis_kelamin'          => $request->jenis_kelamin,
                'asal_sekolah'           => $request->asal_sekolah,
                'jurusan'                => $request->jurusan,
                'tanggal_mulai_magang'   => $request->tanggal_mulai_magang,
                'tanggal_selesai_magang' => $request->tanggal_selesai_magang,
                'status'                 => 'pending',
                'status_aktif'           => false,
                'rejection_reason'       => null,
            ]);
            $rejected->load('office:id,name');
            return response()->json([
                'message' => 'Pengajuan ulang berhasil dikirim. Menunggu persetujuan admin.',
                'data'    => $rejected,
            ], 200);
        }

        // Buat record baru jika belum pernah ada pengajuan sebelumnya
        $member = Member::create([
            'user_id'                => $user->id,
            'no_hp'                  => $noHp,
            'office_id'              => $request->office_id,
            'nama_lengkap'           => $user->name,
            'jenis_kelamin'          => $request->jenis_kelamin,
            'asal_sekolah'           => $request->asal_sekolah,
            'jurusan'                => $request->jurusan,
            'tanggal_mulai_magang'   => $request->tanggal_mulai_magang,
            'tanggal_selesai_magang' => $request->tanggal_selesai_magang,
            'status_aktif'           => false,
            'status'                 => 'pending',
            'created_by'             => null,
        ]);

        $member->load('office:id,name');

        return response()->json([
            'message' => 'Pengajuan berhasil dikirim. Menunggu persetujuan admin.',
            'data'    => $member,
        ], 201);
    }

    /**
     * Get current user's member application status.
     */
    public function myStatus(Request $request)
    {
        $user = $request->user();

        $member = Member::where('user_id', $user->id)
            ->with('office:id,name')
            ->latest()
            ->first();

        return response()->json([
            'data' => $member,
        ]);
    }

    /**
     * Get count of pending member applications.
     */
    public function pendingCount(Request $request)
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $count = Member::where('status', 'pending')->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Admin approves a pending member application.
     */
    public function approve(Request $request, $id)
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $member = Member::findOrFail($id);

        if ($member->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan pending yang bisa disetujui.'], 422);
        }

        $member->update([
            'status' => 'approved',
            'status_aktif' => true,
            'rejection_reason' => null,
        ]);

        $member->refresh();

        try {
            $member->load(['office:id,name']);
            if ($member->user_id) {
                $member->load(['user:id,name,email']);
            }
        } catch (\Exception $e) {
            // Relationship load failed, but data is saved
        }

        return response()->json([
            'message' => 'Member berhasil disetujui.',
            'data' => $member,
        ]);
    }

    /**
     * Admin rejects a pending member application.
     */
    public function reject(Request $request, $id)
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $member = Member::findOrFail($id);

        if ($member->status !== 'pending') {
            return response()->json(['message' => 'Hanya pengajuan pending yang bisa ditolak.'], 422);
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $member->update([
            'status' => 'rejected',
            'status_aktif' => false,
            'rejection_reason' => $request->rejection_reason,
        ]);

        $member->refresh();

        return response()->json([
            'message' => 'Pengajuan ditolak.',
            'data' => $member,
        ]);
    }
}
