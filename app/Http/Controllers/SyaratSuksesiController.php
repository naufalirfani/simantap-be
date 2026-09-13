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
            'syarat' => 'nullable|array',
            'gunakan_kompetensi_teknis' => 'nullable|boolean',
            'sesuai_rumpun_jabatan' => 'nullable|boolean',
            'minimal_usia' => 'nullable|integer|min:15|max:100',
            'maksimal_usia' => 'nullable|integer|min:15|max:100',
            'pangkat_golongan' => 'nullable|string|max:10',
        ]);

        $syaratInput = $data['syarat'] ?? [];
        if (!is_array($syaratInput)) {
            $syaratInput = [];
        }

        // Validate keys are UUIDs and correspond to existing SubIndikator ids
        $keys = array_keys($syaratInput);
        if (!empty($keys)) {
            $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
            foreach ($keys as $k) {
                if (!preg_match($uuidPattern, (string) $k)) {
                    return response()->json(['message' => "Invalid subindikator_id: {$k}"], 422);
                }
            }

            $existing = SubIndikator::whereIn('id', $keys)->pluck('id')->all();
            if (count($existing) !== count($keys)) {
                $missing = array_diff($keys, $existing);
                return response()->json(['message' => 'Some subindikator_id not found', 'missing' => array_values($missing)], 422);
            }
        }

        // Normalize and store syarat values as numbers with 2 decimal places
        $syarat = [];
        foreach ($syaratInput as $k => $value) {
            if ($value !== null && $value !== '') {
                $syarat[$k] = round((float) $value, 2);
            }
        }

        $record = SyaratSuksesi::updateOrCreate(
            ['jabatan_id' => $data['jabatan_id']],
            [
                'syarat' => $syarat,
                'gunakan_kompetensi_teknis' => (bool) ($data['gunakan_kompetensi_teknis'] ?? false),
                'sesuai_rumpun_jabatan' => (bool) ($data['sesuai_rumpun_jabatan'] ?? false),
                'minimal_usia' => isset($data['minimal_usia']) && $data['minimal_usia'] !== '' ? (int) $data['minimal_usia'] : null,
                'maksimal_usia' => isset($data['maksimal_usia']) && $data['maksimal_usia'] !== '' ? (int) $data['maksimal_usia'] : null,
                'pangkat_golongan' => !empty($data['pangkat_golongan']) ? $data['pangkat_golongan'] : null,
            ]
        );

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
            'syarat' => 'sometimes|nullable|array',
            'gunakan_kompetensi_teknis' => 'nullable|boolean',
            'sesuai_rumpun_jabatan' => 'nullable|boolean',
            'minimal_usia' => 'nullable|integer|min:15|max:100',
            'maksimal_usia' => 'nullable|integer|min:15|max:100',
            'pangkat_golongan' => 'nullable|string|max:10',
        ]);

        $record = SyaratSuksesi::find($id);
        if (!$record) {
            $record = SyaratSuksesi::where('jabatan_id', $id)->first();
        }

        if (!$record) {
            return response()->json(['message' => 'Syarat suksesi tidak ditemukan'], 404);
        }

        if (array_key_exists('syarat', $data)) {
            $syaratInput = $data['syarat'] ?? [];
            if (!is_array($syaratInput)) {
                $syaratInput = [];
            }

            $keys = array_keys($syaratInput);
            if (!empty($keys)) {
                $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
                foreach ($keys as $k) {
                    if (!preg_match($uuidPattern, (string) $k)) {
                        return response()->json(['message' => "Invalid subindikator_id: {$k}"], 422);
                    }
                }

                $existing = SubIndikator::whereIn('id', $keys)->pluck('id')->all();
                if (count($existing) !== count($keys)) {
                    $missing = array_diff($keys, $existing);
                    return response()->json(['message' => 'Some subindikator_id not found', 'missing' => array_values($missing)], 422);
                }
            }

            // Normalize and store syarat values
            $syarat = [];
            foreach ($syaratInput as $k => $value) {
                if ($value !== null && $value !== '') {
                    $syarat[$k] = round((float) $value, 2);
                }
            }
            $record->syarat = $syarat;
        }
        
        if (isset($data['jabatan_id'])) {
            $record->jabatan_id = $data['jabatan_id'];
        }

        if ($request->has('gunakan_kompetensi_teknis')) {
            $record->gunakan_kompetensi_teknis = (bool) $request->input('gunakan_kompetensi_teknis');
        }

        if ($request->has('sesuai_rumpun_jabatan')) {
            $record->sesuai_rumpun_jabatan = (bool) $request->input('sesuai_rumpun_jabatan');
        }

        if ($request->has('minimal_usia')) {
            $val = $request->input('minimal_usia');
            $record->minimal_usia = ($val !== null && $val !== '') ? (int) $val : null;
        }

        if ($request->has('maksimal_usia')) {
            $val = $request->input('maksimal_usia');
            $record->maksimal_usia = ($val !== null && $val !== '') ? (int) $val : null;
        }

        if ($request->has('pangkat_golongan')) {
            $val = $request->input('pangkat_golongan');
            $record->pangkat_golongan = (!empty($val)) ? (string) $val : null;
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
