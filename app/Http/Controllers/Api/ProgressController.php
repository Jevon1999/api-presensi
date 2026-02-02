<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Progress;

class ProgressController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/progress",
     *     tags={"Progress"},
     *     summary="Get list of progress records",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="member_id",
     *         in="query",
     *         description="Filter by member ID",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Filter from start date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Filter to end date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-01-31")
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
        $query = Progress::with('member.office');

        if ($request->has('member_id')) {
            $query->where('member_id', $request->member_id);
        }

        if ($request->has('start_date')) {
            $query->whereDate('tanggal', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('tanggal', '<=', $request->end_date);
        }

        $progresses = $query->latest('tanggal')->paginate(20);
        return response()->json($progresses);
    }


    /**
     * @OA\Post(
     *     path="/api/progress",
     *     tags={"Progress"},
     *     summary="Create a new progress record",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"member_id","tanggal","description"},
     *             @OA\Property(property="member_id", type="string", example="9d8a1234-5678-90ab-cdef-1234567890ab"),
     *             @OA\Property(property="tanggal", type="string", format="date", example="2026-02-02"),
     *             @OA\Property(property="description", type="string", example="Completed API documentation and testing")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Progress created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Progress berhasil dibuat"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error or progress already exists for this date")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'required|string|exists:members,id',
            'tanggal' => 'required|date',
            'description' => 'required|string',
        ]);

        $exists = Progress::where('member_id', $validated['member_id'])
            ->where('tanggal', $validated['tanggal'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Progress untuk tanggal ini sudah ada.'], 422);
        }

        $progress = Progress::create($validated);
        $progress->load('member');
        return response()->json([
            'message' => 'Progress berhasil dibuat',
            'data' => $progress
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/progress/{id}",
     *     tags={"Progress"},
     *     summary="Get specific progress record",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Progress ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Progress not found")
     * )
     */
    public function show(Progress $progress)
    {
        $progress->load('member.office');

        return response()->json([
            'data' => $progress
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/progress/{id}",
     *     tags={"Progress"},
     *     summary="Update progress record",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Progress ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"description"},
     *             @OA\Property(property="description", type="string", example="Updated: Completed API documentation and testing")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Progress updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Progress berhasil diupdate"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Progress not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, Progress $progress)
    {
        $validated = $request->validate([
            'description' => 'required|string',
        ]);

        $progress->update($validated);
        
        return response()->json([
            'message' => 'Progress berhasil diupdate',
            'data' => $progress
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/progress/{id}",
     *     tags={"Progress"},
     *     summary="Delete a progress record",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Progress ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Progress deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Progress berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Progress not found")
     * )
     */
    public function destroy(Progress $progress)
    {
        $progress->delete();

        return response()->json([
            'message' => 'Progress berhasil dihapus'
        ]);
    }
}
