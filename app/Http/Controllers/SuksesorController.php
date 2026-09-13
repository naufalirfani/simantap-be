<?php

namespace App\Http\Controllers;

use App\Models\Suksesor;
use App\Models\PetaJabatan;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SuksesorController extends Controller
{
    /**
     * Display a listing of all suksesor.
     */
    public function index()
    {
        try {
            $suksesorList = Suksesor::with(['pegawai', 'petaJabatan'])->get();

            return response()->json([
                'success' => true,
                'data' => $suksesorList,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suksesor list',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the suksesor for a specific peta_jabatan.
     */
    public function show(string $peta_jabatan_id)
    {
        try {
            $suksesor = Suksesor::with(['pegawai', 'petaJabatan'])
                ->where('peta_jabatan_id', $peta_jabatan_id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => $suksesor,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suksesor',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store or update a suksesor for a specific jabatan.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'peta_jabatan_id' => 'required|uuid|exists:peta_jabatan,id',
                'pegawai_id' => 'required|uuid|exists:pegawai,id',
            ]);

            // Ensure employee is not already a successor for another position
            $existing = Suksesor::with('petaJabatan')
                ->where('pegawai_id', $validated['pegawai_id'])
                ->where('peta_jabatan_id', '!=', $validated['peta_jabatan_id'])
                ->first();

            if ($existing) {
                $jabatanName = $existing->petaJabatan?->nama_jabatan ?? 'jabatan lain';
                return response()->json([
                    'success' => false,
                    'message' => "Pegawai ini sudah dipilih sebagai suksesor untuk {$jabatanName}",
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $suksesor = Suksesor::updateOrCreate(
                ['peta_jabatan_id' => $validated['peta_jabatan_id']],
                ['pegawai_id' => $validated['pegawai_id']]
            );

            $suksesor->load(['pegawai', 'petaJabatan']);

            return response()->json([
                'success' => true,
                'data' => $suksesor,
                'message' => 'Suksesor berhasil ditetapkan',
            ], Response::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save suksesor',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancel/delete a suksesor for a specific jabatan.
     */
    public function destroy(string $peta_jabatan_id)
    {
        try {
            $deleted = Suksesor::where('peta_jabatan_id', $peta_jabatan_id)->delete();

            return response()->json([
                'success' => true,
                'message' => $deleted ? 'Pilihan suksesor berhasil dibatalkan' : 'Tidak ada suksesor yang perlu dibatalkan',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel suksesor',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

