<?php

namespace App\Http\Controllers;

use App\Models\SyaratSuksesi;
use App\Models\SubIndikator;
use App\Models\PetaJabatan;
use Illuminate\Http\Request;

class SyaratSuksesiController extends Controller
{
    /**
     * Display a listing of syarat suksesi.
     * Can filter by jabatan_id.
     */
    public function index(Request $request)
    {
        try {
            $query = SyaratSuksesi::query();
            
            if ($request->filled('jabatan_id')) {
                $query->where('jabatan_id', $request->get('jabatan_id'));
            }

            $perPage = (int) $request->get('per_page', 15);
            $perPage = max(1, min($perPage, 100));

            return response()->json($query->orderBy('id', 'desc')->paginate($perPage));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created syarat suksesi.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'jabatan_id' => 'required|exists:peta_jabatan,id',
            'syarat' => 'required|array',
        ]);

        // Validate keys are UUIDs and correspond to existing SubIndikator ids
        $keys = array_keys($data['syarat']);
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        foreach ($keys as $k) {
            if (!preg_match($uuidPattern, $k)) {
                return response()->json(['message' => "Invalid subindikator_id: {$k}"], 422);
            }
        }

        $existing = SubIndikator::whereIn('id', $keys)->pluck('id')->all();
        if (count($existing) !== count($keys)) {
            $missing = array_diff($keys, $existing);
            return response()->json(['message' => 'Some subindikator_id not found', 'missing' => array_values($missing)], 422);
        }

        // Normalize and store syarat values as numbers with 2 decimal places
        $syarat = [];
        foreach ($data['syarat'] as $k => $value) {
            $syarat[$k] = round((float) $value, 2);
        }

        $record = SyaratSuksesi::create([
            'jabatan_id' => $data['jabatan_id'],
            'syarat' => $syarat,
        ]);

        return response()->json($record, 201);
    }

    /**
     * Display the specified syarat suksesi.
     */
    public function show($id)
    {
        // Treat $id as jabatan_id: find the latest syarat suksesi for this jabatan
        $jabatan = PetaJabatan::where('id', $id)->first();

        if (!$jabatan) {
            return response()->json(null, 200);
        }

        $record = SyaratSuksesi::where('jabatan_id', $jabatan->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$record) {
            return response()->json(null, 200);
        }

        return response()->json($record);
    }

    /**
     * Update the specified syarat suksesi.
     */
    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'jabatan_id' => 'sometimes|required|exists:peta_jabatan,id',
            'syarat' => 'sometimes|required|array',
        ]);

        $record = SyaratSuksesi::findOrFail($id);

        if (isset($data['syarat'])) {
            $keys = array_keys($data['syarat']);
            $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
            foreach ($keys as $k) {
                if (!preg_match($uuidPattern, $k)) {
                    return response()->json(['message' => "Invalid subindikator_id: {$k}"], 422);
                }
            }

            $existing = SubIndikator::whereIn('id', $keys)->pluck('id')->all();
            if (count($existing) !== count($keys)) {
                $missing = array_diff($keys, $existing);
                return response()->json(['message' => 'Some subindikator_id not found', 'missing' => array_values($missing)], 422);
            }

            // Normalize and store syarat values
            $syarat = [];
            foreach ($data['syarat'] as $k => $value) {
                $syarat[$k] = round((float) $value, 2);
            }
            $record->syarat = $syarat;
        }
        
        if (isset($data['jabatan_id'])) {
            $record->jabatan_id = $data['jabatan_id'];
        }

        $record->save();

        return response()->json($record);
    }

    /**
     * Remove the specified syarat suksesi.
     */
    public function destroy($id)
    {
        $record = SyaratSuksesi::findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }
}
