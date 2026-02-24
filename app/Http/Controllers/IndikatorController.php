<?php

namespace App\Http\Controllers;

use App\Models\Indikator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class IndikatorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $indikators = Indikator::with('subIndikators')->get();
        return response()->json([
            'success' => true,
            'data' => $indikators
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'indikator' => 'required|string|max:255',
            'bobot' => 'required|numeric|min:0|max:999.99',
            'penilaian' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check total bobot of all indikator (including this new one) does not exceed 100
        // Exclude indikators with penilaian 'Tambahan' from calculation
        $penilaian = $request->input('penilaian');
        if ($penilaian !== 'Tambahan') {
            $newBobot = (float) $request->input('bobot');
            $currentTotal = (float) Indikator::where('penilaian', '!=', 'Tambahan')->sum('bobot');
            $attemptedTotal = $currentTotal + $newBobot;
            if ($attemptedTotal > 100.0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total bobot indikator tidak boleh melebihi 100',
                    'meta' => [
                        'attempted_total' => $attemptedTotal,
                        'current_total' => $currentTotal,
                    ]
                ], 422);
            }
        }

        $indikator = DB::transaction(function () use ($request) {
            return Indikator::create($request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'Indikator berhasil dibuat',
            'data' => $indikator
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $indikator = Indikator::with('subIndikators')->find($id);

        if (!$indikator) {
            return response()->json([
                'success' => false,
                'message' => 'Indikator tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $indikator
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $indikator = Indikator::find($id);

        if (!$indikator) {
            return response()->json([
                'success' => false,
                'message' => 'Indikator tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'indikator' => 'sometimes|string|max:255',
            'bobot' => 'sometimes|numeric|min:0|max:999.99',
            'penilaian' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $willChangeBobot = $request->has('bobot');
        $targetPenilaian = $request->input('penilaian', $indikator->penilaian);

        // Only validate bobot total if penilaian is not 'Tambahan'
        if ($willChangeBobot && $targetPenilaian !== 'Tambahan') {
            $newBobot = (float) $request->input('bobot');
            $sumOthers = (float) Indikator::where('id', '!=', $id)
                ->where('penilaian', '!=', 'Tambahan')
                ->sum('bobot');
            $total = $sumOthers + $newBobot;
            if ($total > 100.0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total bobot indikator tidak boleh melebihi 100',
                    'meta' => [
                        'attempted_total' => $total,
                        'current_total' => $sumOthers,
                    ]
                ], 422);
            }
        }

        DB::transaction(function () use ($indikator, $request, $willChangeBobot) {
            $indikator->update($request->all());

            if ($willChangeBobot) {
                $indikator->subIndikators()->update(['bobot' => 0]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Indikator berhasil diupdate',
            'data' => $indikator
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $indikator = Indikator::find($id);

        if (!$indikator) {
            return response()->json([
                'success' => false,
                'message' => 'Indikator tidak ditemukan'
            ], 404);
        }

        // Ensure child subindikators are removed as well
        // DB foreign key cascade should handle this, but delete via relationship to be explicit
        if ($indikator->subIndikators()->exists()) {
            $indikator->subIndikators()->delete();
        }

        $indikator->delete();

        return response()->json([
            'success' => true,
            'message' => 'Indikator berhasil dihapus'
        ], 200);
    }
}
