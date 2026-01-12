<?php

namespace App\Http\Controllers;

use App\Models\Instrumen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InstrumenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $instrumens = Instrumen::with('subindikator')->get();
        return response()->json([
            'success' => true,
            'data' => $instrumens
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'instrumen' => 'required|string|max:255',
            'skor' => 'required|numeric|min:0|max:999.99',
            'subindikator_id' => 'required|uuid|exists:subindikators,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $instrumen = Instrumen::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Instrumen berhasil dibuat',
            'data' => $instrumen->load('subIndikator')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $instrumen = Instrumen::with('subIndikator')->find($id);

        if (!$instrumen) {
            return response()->json([
                'success' => false,
                'message' => 'Instrumen tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $instrumen
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $instrumen = Instrumen::find($id);

        if (!$instrumen) {
            return response()->json([
                'success' => false,
                'message' => 'Instrumen tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'instrumen' => 'sometimes|string|max:255',
            'skor' => 'sometimes|numeric|min:0|max:999.99',
            'subindikator_id' => 'sometimes|uuid|exists:subindikators,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $instrumen->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Instrumen berhasil diupdate',
            'data' => $instrumen->load('subIndikator')
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $instrumen = Instrumen::find($id);

        if (!$instrumen) {
            return response()->json([
                'success' => false,
                'message' => 'Instrumen tidak ditemukan'
            ], 404);
        }

        $instrumen->delete();

        return response()->json([
            'success' => true,
            'message' => 'Instrumen berhasil dihapus'
        ], 200);
    }
}
