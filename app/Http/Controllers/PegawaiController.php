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
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $perPage = min(max((int)$perPage, 1), 100);

            $query = Pegawai::query();

            // `key` performs a broad search across most text columns (EXCLUDING the json column)
            // Example: ?key=andi will match name, nip, email, unit_organisasi_name, jabatan_name, jenis_jabatan, golongan
            if ($request->filled('q')) {
                $k = $request->get('q');
                $query->where(function ($q) use ($k) {
                    $q->where('name', 'ilike', "%{$k}%")
                      ->orWhere('nip', 'ilike', "%{$k}%")
                      ->orWhere('email', 'ilike', "%{$k}%")
                      ->orWhere('unit_organisasi_name', 'ilike', "%{$k}%")
                      ->orWhere('jabatan_name', 'ilike', "%{$k}%")
                      ->orWhere('jenis_jabatan', 'ilike', "%{$k}%")
                      ->orWhere('golongan', 'ilike', "%{$k}%");
                });
            }

            // Support `filter` parameter using keys from materialized view `statistik`.
            // Accepts values like: struktural, fungsional, pelaksana, laki_laki, perempuan,
            // total_jabatan_pimpinan_tinggi_madya, total_fungsional_utama, etc.
            if ($request->filled('filter')) {
                $filter = strtolower($request->get('filter'));

                $map = [
                    'struktural' => function ($q) { $q->where('jenis_jabatan', 'Struktural'); },
                    'fungsional' => function ($q) { $q->where('jenis_jabatan', 'Fungsional'); },
                    'pelaksana' => function ($q) { $q->where('jenis_jabatan', 'Pelaksana'); },

                    'laki_laki' => function ($q) { $q->whereRaw("json->>'jenisKelamin' = ?", ['M']); },
                    'perempuan' => function ($q) { $q->whereRaw("json->>'jenisKelamin' = ?", ['F']); },

                    'jabatan_pimpinan_tinggi_madya' => function ($q) { $q->whereRaw("json->>'eselonLevel' = ?", ['1']); },
                    'jabatan_pimpinan_tinggi_pratama' => function ($q) { $q->whereRaw("json->>'eselonLevel' = ?", ['2']); },
                    'jabatan_administrator' => function ($q) { $q->whereRaw("json->>'eselonLevel' = ?", ['3']); },
                    'jabatan_pengawas' => function ($q) { $q->whereRaw("json->>'eselonLevel' = ?", ['4']); },

                    'fungsional_utama' => function ($q) { $q->whereRaw("jabatan_name ~ 'Ahli Utama'"); },
                    'fungsional_madya' => function ($q) { $q->whereRaw("jabatan_name ~ 'Ahli Madya'"); },
                    'fungsional_muda' => function ($q) { $q->whereRaw("jabatan_name ~ 'Ahli Muda'"); },
                    'fungsional_pertama' => function ($q) { $q->whereRaw("jabatan_name ~ 'Ahli Pertama'"); },

                    'fungsional_penyelia' => function ($q) { $q->whereRaw("jabatan_name ~ 'Penyelia'"); },
                    'fungsional_mahir' => function ($q) { $q->whereRaw("jabatan_name ~ 'Mahir'"); },
                    'fungsional_terampil' => function ($q) { $q->whereRaw("jabatan_name ~ 'Terampil'"); },
                ];

                if (isset($map[$filter])) {
                    $map[$filter]($query);
                } else {
                    // allow variants without prefixes, e.g. 'struktural' already covered; if unknown, return 400
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid filter provided',
                    ], 400);
                }
            }

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
                    'jenis_jabatan' => $item->jenis_jabatan,
                    'golongan' => $item->golongan,
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
            $pegawai = Pegawai::where('nip', $nip)->first();

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
