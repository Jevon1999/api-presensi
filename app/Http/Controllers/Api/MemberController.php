<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Member;

class MemberController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/members",
     *     tags={"Members"},
     *     summary="Get list of members",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="office_id",
     *         in="query",
     *         description="Filter by office ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status_aktif",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name or phone number",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        // Auto-deactivate expired members dynamically just before viewing
        Member::where('status_aktif', true)
            ->whereNotNull('tanggal_selesai_magang')
            ->whereDate('tanggal_selesai_magang', '<', now()->toDateString())
            ->update(['status_aktif' => false]);

        $query = Member::with(['office', 'creator']);
        
        //filter by office
        if ($request->has('office_id')) {
            $query->where('office_id', $request->office_id);
        }

        //filter by status
        if ($request->has('status_aktif')) {
            $query->where('status_aktif', $request->status_aktif);
        }

        //filter by application status (pending/approved/rejected)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        //search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orwhere('no_hp', 'like', "%{$search}%");
            });
        }

        $members = $query->latest()->paginate(20);
        
        return response()->json($members);
    }

    /**
     * @OA\Post(
     *     path="/api/members",
     *     tags={"Members"},
     *     summary="Create a new member",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"no_hp","office_id","nama_lengkap","jenis_kelamin","asal_sekolah","tanggal_mulai_magang"},
     *             @OA\Property(property="no_hp", type="string", example="+6281234567890"),
     *             @OA\Property(property="office_id", type="integer", example=1),
     *             @OA\Property(property="nama_lengkap", type="string", example="John Doe"),
     *             @OA\Property(property="jenis_kelamin", type="string", enum={"L", "P"}, example="L"),
     *             @OA\Property(property="asal_sekolah", type="string", example="Universitas ABC"),
     *             @OA\Property(property="tanggal_mulai_magang", type="string", format="date", example="2026-01-15"),
     *             @OA\Property(property="tanggal_selesai_magang", type="string", format="date", example="2026-03-15"),
     *             @OA\Property(property="status_aktif", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Member created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Member berhasil dibuat"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'no_hp' => 'required|string|unique:members,no_hp|max:15',
            'office_id' => 'required|exists:offices,id',
            'user_id' => 'nullable|exists:users,id',
            'nama_lengkap' => 'required|string|max:255',
            'jenis_kelamin' => 'required|in:L,P',
            'asal_sekolah' => 'required|string|max:255',
            'jurusan' => 'nullable|string|max:255',
            'tanggal_mulai_magang' => 'required|date',
            'tanggal_selesai_magang' => 'nullable|date|after_or_equal:tanggal_mulai_magang',
            'status_aktif' => 'boolean',
        ]);

        $validated['created_by'] = auth()->id();
        // Admin yang tambah member manual langsung approved & aktif
        $validated['status'] = 'approved';
        if (!isset($validated['status_aktif'])) {
            $validated['status_aktif'] = true;
        }

        // Normalize phone number to +62 format
        if (!empty($validated['no_hp'])) {
            $noHp = preg_replace('/[^\d+]/', '', $validated['no_hp']);
            if (preg_match('/^08/', $noHp)) {
                $noHp = '+62' . substr($noHp, 1);
            } elseif (preg_match('/^628/', $noHp)) {
                $noHp = '+' . $noHp;
            } elseif (preg_match('/^8\d{8,}/', $noHp)) {
                $noHp = '+62' . $noHp;
            }
            $validated['no_hp'] = $noHp;
        }

        $member = Member::create($validated);
        $member->load('office');

        return response()->json([
            'message' => 'Member berhasil dibuat',
            'data' => $member,
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/members/{id}",
     *     tags={"Members"},
     *     summary="Get specific member",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Member ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Member not found")
     * )
     */
    public function show(string $id)
    {
        $member = Member::with(['office', 'creator'])->find($id);

        if (!$member) {
            return response()->json(['message' => 'Member tidak ditemukan'], 404);
        }

        return response()->json(['data' => $member]);
    }

    /**
     * @OA\Put(
     *     path="/api/members/{id}",
     *     tags={"Members"},
     *     summary="Update member details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Member ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="no_hp", type="string", example="+6281234567890"),
     *             @OA\Property(property="office_id", type="integer", example=1),
     *             @OA\Property(property="nama_lengkap", type="string", example="John Doe"),
     *             @OA\Property(property="jenis_kelamin", type="string", enum={"L", "P"}, example="L"),
     *             @OA\Property(property="asal_sekolah", type="string", example="Universitas ABC"),
     *             @OA\Property(property="tanggal_mulai_magang", type="string", format="date", example="2026-01-15"),
     *             @OA\Property(property="tanggal_selesai_magang", type="string", format="date", example="2026-03-15"),
     *             @OA\Property(property="status_aktif", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Member updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Member berhasil diupdate"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Member not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, string $id)
    {
        $member = Member::find($id);

        if (!$member) {
            return response()->json(['message' => 'Member tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'no_hp' => 'sometimes|required|string|unique:members,no_hp,' . $id . '|max:15',
            'office_id' => 'sometimes|required|exists:offices,id',
            'user_id' => 'sometimes|nullable|exists:users,id',
            'nama_lengkap' => 'sometimes|required|string|max:255',
            'jenis_kelamin' => 'sometimes|required|in:L,P',
            'asal_sekolah' => 'sometimes|required|string|max:255',
            'jurusan' => 'nullable|string|max:255',
            'tanggal_mulai_magang' => 'sometimes|required|date',
            'tanggal_selesai_magang' => 'nullable|date|after_or_equal:tanggal_mulai_magang',
            'status_aktif' => 'boolean',
        ]);

        // Normalize phone number to +62 format
        if (!empty($validated['no_hp'])) {
            $noHp = preg_replace('/[^\d+]/', '', $validated['no_hp']);
            if (preg_match('/^08/', $noHp)) {
                $noHp = '+62' . substr($noHp, 1);
            } elseif (preg_match('/^628/', $noHp)) {
                $noHp = '+' . $noHp;
            } elseif (preg_match('/^8\d{8,}/', $noHp)) {
                $noHp = '+62' . $noHp;
            }
            $validated['no_hp'] = $noHp;
        }

        $member->update($validated);
        $member->load('office');

        return response()->json([
            'message' => 'Member berhasil diupdate',
            'data' => $member,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/members/{id}",
     *     tags={"Members"},
     *     summary="Delete a member",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Member ID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Member deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Member berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Member not found")
     * )
     */
    public function destroy(string $id)
    {
        $member = Member::find($id);

        if (!$member) {
            return response()->json(['message' => 'Member tidak ditemukan'], 404);
        }

        $member->delete();

        return response()->json(['message' => 'Member berhasil dihapus']);
    }
}
