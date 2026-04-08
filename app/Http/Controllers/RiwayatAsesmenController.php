<?php

namespace App\Http\Controllers;

use App\Models\RiwayatAsesmen;
use Illuminate\Http\Request;

class RiwayatAsesmenController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = RiwayatAsesmen::query()
                ->selectRaw('DISTINCT ON (pegawai_id, nama_asesmen) riwayat_asesmen.*')
                ->orderBy('pegawai_id')
                ->orderBy('nama_asesmen')
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            if ($request->filled('pegawai_id')) {
                $query->where('pegawai_id', $request->get('pegawai_id'));
            }

            if ($request->filled('nama_asesmen')) {
                $query->where('nama_asesmen', $request->get('nama_asesmen'));
            }

            $perPage = (int) $request->get('per_page', 15);
            $perPage = max(1, min($perPage, 100));

            return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function uniqueNamaAsesmen()
    {
        try {
            $data = RiwayatAsesmen::query()
                ->select('nama_asesmen')
                ->distinct()
                ->orderBy('nama_asesmen')
                ->pluck('nama_asesmen');

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_asesmen' => 'required|string|max:255',
            'pegawai_id' => 'required|exists:pegawai,id',
            'data_asesmen' => 'required|array',
        ]);

        $record = RiwayatAsesmen::create($data);
        return response()->json($record, 201);
    }

    public function show($id)
    {
        try {
            $record = RiwayatAsesmen::findOrFail($id);

            return response()->json($record);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function showByPegawai($pegawai_id)
    {
        try {
            $data = RiwayatAsesmen::query()
                ->selectRaw('DISTINCT ON (pegawai_id, nama_asesmen) riwayat_asesmen.*')
                ->where('pegawai_id', $pegawai_id)
                ->orderBy('pegawai_id')
                ->orderBy('nama_asesmen')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $record = RiwayatAsesmen::findOrFail($id);

        $data = $request->validate([
            'nama_asesmen' => 'sometimes|required|string|max:255',
            'pegawai_id' => 'sometimes|required|exists:pegawai,id',
            'data_asesmen' => 'sometimes|required|array',
        ]);

        $record->update($data);
        return response()->json($record);
    }

    public function destroy($id)
    {
        $record = RiwayatAsesmen::findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }
}
