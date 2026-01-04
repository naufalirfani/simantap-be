<?php

namespace App\Http\Controllers;

use App\Models\SubIndikator;
use App\Models\Indikator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubIndikatorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $subIndikators = SubIndikator::with(['indikator', 'instrumens'])->get();
        return response()->json([
            'success' => true,
            'data' => $subIndikators
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subindikator' => 'required|string|max:255',
            'bobot' => 'required|numeric|min:0|max:999.99',
            'isactive' => 'required|boolean',
            'indikator_id' => 'required|uuid|exists:indikators,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check sibling sum does not exceed parent indikator bobot
        $indikatorId = $request->input('indikator_id');
        $newBobot = (float) $request->input('bobot');
        $parent = Indikator::find($indikatorId);
        $currentSiblingTotal = (float) SubIndikator::where('indikator_id', $indikatorId)->sum('bobot');
        if ($currentSiblingTotal + $newBobot > (float) $parent->bobot) {
            return response()->json([
                'success' => false,
                'message' => 'Total bobot subindikator tidak boleh melebihi bobot indikator induk',
                'meta' => [
                    'attempted_total' => $currentSiblingTotal + $newBobot,
                    'bobot_indikator' => (float) $parent->bobot,
                    'current_total' => $currentSiblingTotal,
                ]
            ], 422);
        }

        $subIndikator = DB::transaction(function () use ($request) {
            return SubIndikator::create($request->all());
        });

        return response()->json([
            'success' => true,
            'message' => 'SubIndikator berhasil dibuat',
            'data' => $subIndikator->load('indikator')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $subIndikator = SubIndikator::with(['indikator', 'instrumens'])->find($id);

        if (!$subIndikator) {
            return response()->json([
                'success' => false,
                'message' => 'SubIndikator tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $subIndikator
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $subIndikator = SubIndikator::find($id);

        if (!$subIndikator) {
            return response()->json([
                'success' => false,
                'message' => 'SubIndikator tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'subindikator' => 'sometimes|string|max:255',
            'bobot' => 'sometimes|numeric|min:0|max:999.99',
            'isactive' => 'sometimes|boolean',
            'indikator_id' => 'sometimes|uuid|exists:indikators,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // If bobot or indikator_id is changing, validate sibling total vs parent
        if ($request->has('bobot') || $request->has('indikator_id')) {
            $targetIndikatorId = $request->input('indikator_id', $subIndikator->indikator_id);
            $newBobot = $request->has('bobot') ? (float) $request->input('bobot') : (float) $subIndikator->bobot;
            $parent = Indikator::find($targetIndikatorId);
            $currentSiblingTotal = (float) SubIndikator::where('indikator_id', $targetIndikatorId)
                ->where('id', '!=', $id)
                ->sum('bobot');

            if ($currentSiblingTotal + $newBobot > (float) $parent->bobot) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total bobot subindikator tidak boleh melebihi bobot indikator induk',
                    'meta' => [
                        'attempted_total' => $currentSiblingTotal + $newBobot,
                        'bobot_indikator' => (float) $parent->bobot,
                        'current_total' => $currentSiblingTotal,
                    ]
                ], 422);
            }
        }

        $updated = DB::transaction(function () use ($subIndikator, $request) {
            $subIndikator->update($request->all());
            return $subIndikator->load('indikator');
        });

        return response()->json([
            'success' => true,
            'message' => 'SubIndikator berhasil diupdate',
            'data' => $updated
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $subIndikator = SubIndikator::find($id);

        if (!$subIndikator) {
            return response()->json([
                'success' => false,
                'message' => 'SubIndikator tidak ditemukan'
            ], 404);
        }

        $subIndikator->delete();

        return response()->json([
            'success' => true,
            'message' => 'SubIndikator berhasil dihapus'
        ], 200);
    }
}
