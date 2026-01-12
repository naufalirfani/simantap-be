<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class PegawaiController extends Controller
{
    /**
     * Manually trigger pegawai sync from external API
     */
    public function sync()
    {
        // Set time limit to 10 minutes for sync operation
        set_time_limit(600);
        
        try {
            Artisan::call('sync:pegawai');
            $output = Artisan::output();

            // Normalize line endings and extract summary (Inserted/Updated/Errors)
            $output = str_replace("\r", '', $output);
            $summary = null;
            if (preg_match('/\(Inserted:\s*\d+,\s*Updated:\s*\d+,\s*Errors:\s*\d+\)/', $output, $m)) {
                $summary = $m[0];
            }

            $completed = strpos($output, 'Synchronization completed successfully!') !== false;

            (new StatistikController())->sync();
            return response()->json([
                'success' => true,
                'summary' => $summary ?? '',
                'message' => $completed ? 'Synchronization completed successfully!' : 'Synchronization triggered',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to trigger synchronization',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a listing of pegawai.
     * Returns: Nama, Email, Avatar, NIP, Unit Kerja, Jabatan, Lokasi Kerja, Jenis Jabatan, Golongan
     */
    public function index(Request $request, bool $withPenilaian = false)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $perPage = min(max((int)$perPage, 1), 100);

            // allow overriding the method param via query param ?withPenilaian=true
            $withPenilaian = $request->boolean('withPenilaian', $withPenilaian);

            // join to peta_jabatan and jenis_jabatan so we can order and select jenis_jabatan name
            $query = Pegawai::query()
                ->when($withPenilaian, function ($q) {
                    $q->with('penilaian');
                })
                ->leftJoin('peta_jabatan', 'pegawai.peta_jabatan_id', '=', 'peta_jabatan.id')
                ->leftJoin('jenis_jabatan', 'pegawai.jenis_jabatan_id', '=', 'jenis_jabatan.id')
                ->select('pegawai.*', 'jenis_jabatan.name as jenis_jabatan');

            // `key` performs a broad search across most text columns (EXCLUDING the json column)
            // Example: ?key=andi will match name, nip, email, unit_organisasi_name, jabatan_name, jenis_jabatan, golongan
            if ($request->filled('q')) {
                $k = $request->get('q');
                                $query->where(function ($q) use ($k) {
                                        $q->where('pegawai.name', 'ilike', "%{$k}%")
                                            ->orWhere('nip', 'ilike', "%{$k}%")
                                            ->orWhere('email', 'ilike', "%{$k}%")
                                            ->orWhere('unit_organisasi_name', 'ilike', "%{$k}%")
                                            ->orWhere('jabatan_name', 'ilike', "%{$k}%")
                                            ->orWhere('jenis_jabatan.name', 'ilike', "%{$k}%")
                                            ->orWhere('golongan', 'ilike', "%{$k}%");
                                });
            }

            if ($request->filled('unit_organisasi_name')) {
                $unitName = $request->get('unit_organisasi_name');
                $query->whereRaw('lower(unit_organisasi_name) = ?', [strtolower($unitName)]);
            }
            if ($request->filled('jabatan_name')) {
                $jabatanName = $request->get('jabatan_name');
                $query->whereRaw('lower(jabatan_name) = ?', [strtolower($jabatanName)]);
            }
            if ($request->filled('jenis_jabatan')) {
                $jenisJabatan = $request->get('jenis_jabatan');
                $query->whereRaw('lower(jenis_jabatan.name) = ?', [strtolower($jenisJabatan)]);
            }
            if ($request->filled('golongan')) {
                $golongan = $request->get('golongan');
                $query->whereRaw('lower(golongan) = ?', [strtolower($golongan)]);
            }

            // Order first by peta_jabatan.kelas_jabatan interpreted as number (desc, NULLS LAST), then by name
            // Use regex to ensure only pure digits are cast to avoid errors on non-numeric values.
            $query->orderByRaw("(CASE WHEN peta_jabatan.kelas_jabatan ~ '^[0-9]+$' THEN CAST(peta_jabatan.kelas_jabatan AS integer) ELSE NULL END) DESC NULLS LAST");
            $pegawai = $query->orderBy('name')->paginate($perPage);

            // Transform data to only include required fields
            $data = $pegawai->map(function ($item) {
                return [
                    'nip' => $item->nip,
                    'nama' => $item->name,
                    'email' => $item->email,
                    'avatar' => $item->avatar,
                    'unit_kerja' => $item->unit_organisasi_name,
                    'jabatan' => $item->jabatan_name,
                    'lokasi_kerja' => $item->lokasi_kerja,
                    'jenis_jabatan' => str_replace('Jabatan Fungsional', 'JF', str_replace('Jabatan Pimpinan Tinggi', 'JPT', $item->jenis_jabatan)),
                    'golongan' => $item->golongan,
                    'penilaian' => $item->penilaian ? $item->penilaian->penilaian : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'current_page' => $pegawai->currentPage(),
                    'per_page' => $pegawai->perPage(),
                    'last_page' => $pegawai->lastPage(),
                    'total' => $pegawai->total(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pegawai data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified pegawai by NIP.
     * Returns all fields.
     */
    public function show(string $nip)
    {
        try {
            $pegawai = Pegawai::where('nip', $nip)->join('jenis_jabatan', 'pegawai.jenis_jabatan_id', '=', 'jenis_jabatan.id')->select('pegawai.*', 'jenis_jabatan.name as jenis_jabatan')->first();

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pegawai not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $pegawai->id,
                    'nip' => $pegawai->nip,
                    'nama' => $pegawai->name,
                    'email' => $pegawai->email,
                    'unit_kerja' => $pegawai->unit_organisasi_name,
                    'jabatan' => $pegawai->jabatan_name,
                    'jenis_jabatan' => $pegawai->jenis_jabatan,
                    'golongan' => $pegawai->golongan,
                    'json' => $pegawai->json,
                    'avatar' => $pegawai->avatar,
                    'lokasi_kerja' => $pegawai->lokasi_kerja,
                    'created_at' => $pegawai->created_at,
                    'updated_at' => $pegawai->updated_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pegawai detail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
