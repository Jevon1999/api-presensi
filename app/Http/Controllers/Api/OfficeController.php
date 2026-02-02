<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Office;

class OfficeController extends Controller
{
    /**
     * Display a listing of the resource.
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
     * Store a newly created resource in storage.
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
     * Display the specified resource.
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
     * Update the specified resource in storage.
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
     * Remove the specified resource from storage.
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
