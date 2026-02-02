<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Office;

class OfficeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/offices",
     *     tags={"Offices"},
     *     summary="Get list of all offices",
     *     security={{"bearerAuth":{}}},
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
    public function index()
    {
        $offices = Office::withCount('members')
            ->with('locations')
            ->latest()
            ->get();

        return response()->json([
            'data' => $offices
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/offices",
     *     tags={"Offices"},
     *     summary="Create a new office",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","name"},
     *             @OA\Property(property="code", type="string", example="JKT"),
     *             @OA\Property(property="name", type="string", example="Jakarta Office")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Office created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Office berhasil dibuat"),
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
            'code' => 'required|string|unique:offices,code|max:50',
            'name' => 'required|string|max:255',
        ]);

        $office = Office::create($validated);
        return response()->json([
            'message' => 'Office berhasil dibuat',
            'data' => $office
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/offices/{id}",
     *     tags={"Offices"},
     *     summary="Get specific office with locations and members",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Office ID",
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
     *     @OA\Response(response=404, description="Office not found")
     * )
     */
    public function show(Office $office)
    {
        $office->load(['locations', 'members' => function($q) {
            $q->where('status_aktif', true);
        }]);
        return response()->json([
            'data' => $office
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/offices/{id}",
     *     tags={"Offices"},
     *     summary="Update office details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Office ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","name"},
     *             @OA\Property(property="code", type="string", example="JKT"),
     *             @OA\Property(property="name", type="string", example="Jakarta Office")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Office updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Office berhasil diupdate"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Office not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, Office $office)
    {
        $validated = $request->validate([
            'code' => 'required|max:50|string|unique:offices,code,' . $office->id,
            'name' => 'required|string|max:255',
        ]);

        $office->update($validated);
        return response()->json([
            'message' => 'Office berhasil diupdate',
            'data' => $office
        ]);

    }

    /**
     * @OA\Delete(
     *     path="/api/offices/{id}",
     *     tags={"Offices"},
     *     summary="Delete an office",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Office ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Office deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Office berhasil dihapus")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=404, description="Office not found"),
     *     @OA\Response(response=422, description="Cannot delete office with active members")
     * )
     */
    public function destroy(Office $office)
    {
        if ($office->members()->where('status_aktif', true)->exists()) {
            return response()->json([
                'message' => 'Tidak dapat menghapus office yang masih memliliki member aktif'
            ], 422);
        }

        $office->delete();

        return response()->json([
            'message' => 'Office berhasil dihapus'
        ]);
    }
}
