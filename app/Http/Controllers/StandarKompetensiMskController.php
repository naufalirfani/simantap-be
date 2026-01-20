<?php

namespace App\Http\Controllers;

use App\Models\StandarKompetensiMsk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StandarKompetensiMskController extends Controller
{
    public function index(Request $request)
    {
        if ($request->has('jenis_jabatan_id')) {
            $jenisJabatanId = $request->query('jenis_jabatan_id');
            $query = StandarKompetensiMsk::with(['jenisJabatan', 'subindikator'])
                ->where('jenis_jabatan_id', $jenisJabatanId)
                ->orderBy('id');
            return response()->json($query->get());
        }
        
        $query = StandarKompetensiMsk::with(['jenisJabatan', 'subindikator'])->orderBy('id');
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'jenis_jabatan_id' => 'required|exists:jenis_jabatan,id',
            'subindikator_id' => 'required|exists:subindikators,id',
            'standar' => 'required|integer|min:0|max:5',
        ]);

        $record = StandarKompetensiMsk::create($data);
        return response()->json($record, 201);
    }

    public function show($id)
    {
        $record = StandarKompetensiMsk::with(['jenisJabatan', 'subindikator'])->findOrFail($id);
        return response()->json($record);
    }

    public function update(Request $request, $id)
    {
        $record = StandarKompetensiMsk::findOrFail($id);
        $data = $request->validate([
            'jenis_jabatan_id' => 'sometimes|required|exists:jenis_jabatan,id',
            'subindikator_id' => 'sometimes|required|exists:subindikators,id',
            'standar' => 'sometimes|required|integer|min:0|max:5',
        ]);
        $record->update($data);
        return response()->json($record);
    }

    public function destroy($id)
    {
        $record = StandarKompetensiMsk::findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }

    /**
     * Bulk update standar values.
     * Expects payload: { "updates": [ { "id": "uuid-or-id", "standar": 3 }, ... ] }
     */
    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'msk' => 'required|array|min:1',
            'msk.*.id' => 'required',
            'msk.*.standar' => 'required|integer|min:0|max:5',
        ]);

        $updates = $data['msk'];
        $ids = array_map(function ($u) { return $u['id']; }, $updates);

        $existing = StandarKompetensiMsk::whereIn('id', $ids)->get()->keyBy('id');
        $missing = array_filter($ids, function ($id) use ($existing) { return !isset($existing[$id]); });

        if (count($missing) > 0) {
            return response()->json(['message' => 'Some records not found', 'missing' => array_values($missing)], 422);
        }

        $updated = [];

        DB::transaction(function () use ($updates, $existing, &$updated) {
            foreach ($updates as $u) {
                $rec = $existing[$u['id']];
                $rec->standar = $u['standar'];
                $rec->save();
                $updated[] = $rec;
            }
        });

        return response()->json($updated);
    }
}
