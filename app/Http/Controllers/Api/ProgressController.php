<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Progress;

class ProgressController extends Controller
{
    /**
     * Display a listing of the resource.
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
     * Store a newly created resource in storage.
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
     * Display the specified resource.
     */
    public function show(Progress $progress)
    {
        $progress->load('member.office');

        return response()->json([
            'data' => $progress
        ]);
    }

    /**
     * Update the specified resource in storage.
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
     * Remove the specified resource from storage.
     */
    public function destroy(Progress $progress)
    {
        $progress->delete();

        return response()->json([
            'message' => 'Progress berhasil dihapus'
        ]);
    }
}
