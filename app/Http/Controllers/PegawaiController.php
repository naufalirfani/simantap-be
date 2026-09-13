<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\PetaJabatan;
use App\Models\Suksesor;
use App\Models\SyaratSuksesi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class PegawaiController extends Controller
{
    /**
     * Manually trigger pegawai sync from external API.
     *
     * Optional request params:
     *   nip (string|string[]) – when provided, only re-sync penilaian for the given NIP(s)
     *                           (pegawai data sync still runs for all unless you pass nip to
     *                           the artisan command separately)
     */
    public function sync(Request $request)
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
    public function index(Request $request, bool $withPenilaian = false)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $perPage = min(max((int) $perPage, 1), 100);

            // allow overriding via query params `withPenilaian` or `with_penilaian` (snake_case from FE)
            $withPenilaian = $request->boolean('with_penilaian', $request->boolean('withPenilaian', $withPenilaian));

            $withPagination = $request->boolean('with_pagination', true);

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
            if ($request->filled('sudah_asesmen')) {
                $sudah = $request->boolean('sudah_asesmen');

                $indikatorAsesmenIds = \App\Models\Subindikator::query()
                    ->join('indikators', 'subindikators.indikator_id', '=', 'indikators.id')
                    ->where(function ($q) {
                        $q->where('indikators.indikator', 'Penilaian Kompetensi Manajerial dan Sosial Kultural')
                            ->orWhere('indikators.indikator', 'Penilaian Potensi Talenta');
                    })
                    ->pluck('subindikators.id')
                    ->toArray();

                if (! empty($indikatorAsesmenIds)) {
                    $clauses = [];
                    $bindings = [];
                    foreach ($indikatorAsesmenIds as $id) {
                        $clauses[] = "(COALESCE((penilaians.penilaian -> ? ->> 'nilai'), '0')::numeric > 0 OR COALESCE((penilaians.penilaian -> ? ->> 'hasil'), '0')::numeric > 0)";
                        $bindings[] = $id;
                        $bindings[] = $id;
                    }

                    $sql = implode(' OR ', $clauses);
                    if ($sudah) {
                        $query->whereRaw('EXISTS (SELECT 1 FROM penilaians WHERE penilaians.pegawai_id = pegawai.id AND ('.$sql.'))', $bindings);
                    } else {
                        $query->whereRaw('NOT EXISTS (SELECT 1 FROM penilaians WHERE penilaians.pegawai_id = pegawai.id AND ('.$sql.'))', $bindings);
                    }
                }
            }

            // Order first by peta_jabatan.kelas_jabatan interpreted as number (desc, NULLS LAST), then by name
            // Use regex to ensure only pure digits are cast to avoid errors on non-numeric values.
            $query->orderByRaw("(CASE WHEN peta_jabatan.kelas_jabatan ~ '^[0-9]+$' THEN CAST(peta_jabatan.kelas_jabatan AS integer) ELSE NULL END) DESC NULLS LAST");

            if (! $withPagination) {
                $perPage = PHP_INT_MAX;
            }

            $pegawai = $query->orderBy('name')->paginate($perPage);

            // If requested, preload subindikator -> kategori map so we can compute sums
            $subKategoriMap = null;
            if ($withPenilaian) {
                $subs = \App\Models\SubIndikator::with('indikator')->get();
                $subKategoriMap = [];
                foreach ($subs as $s) {
                    $pid = $s->id;
                    $indikatorPen = strtolower($s->indikator->penilaian ?? '');
                    if (strpos($indikatorPen, 'potensial') !== false || strpos($indikatorPen, 'potensi') !== false) {
                        $subKategoriMap[$pid] = 'potensial';
                    } elseif (strpos($indikatorPen, 'kinerja') !== false) {
                        $subKategoriMap[$pid] = 'kinerja';
                    } else {
                        // default: consider as kinerja
                        $subKategoriMap[$pid] = 'tambahan';
                    }
                }
            }

            // Transform data to only include required fields
            $data = $pegawai->map(function ($item) use ($withPenilaian, $subKategoriMap) {
                $penObj = $item->penilaian ? $item->penilaian->penilaian : null;

                $result = [
                    'id' => $item->id,
                    'nip' => $item->nip,
                    'nama' => $item->name,
                    'email' => $item->email,
                    'avatar' => $item->avatar,
                    'unit_kerja' => $item->unit_organisasi_name,
                    'jabatan' => $item->jabatan_name,
                    'lokasi_kerja' => $item->lokasi_kerja,
                    'jenis_jabatan_id' => $item->jenis_jabatan_id,
                    'jenis_jabatan' => str_replace('Jabatan Fungsional', 'JF', str_replace('Jabatan Pimpinan Tinggi', 'JPT', $item->jenis_jabatan)),
                    'golongan' => $item->golongan,
                    'penilaian' => $penObj,
                ];

                if ($withPenilaian) {
                    $nilaiPot = 0.0;
                    $nilaiKin = 0.0;
                    if (is_array($penObj)) {
                        foreach ($penObj as $subId => $val) {
                            $hasil = null;
                            if (is_array($val) && array_key_exists('hasil', $val)) {
                                $hasil = (float) $val['hasil'];
                            } elseif (is_numeric($val)) {
                                // legacy numeric value stored directly
                                $hasil = (float) $val;
                            }
                            if ($hasil === null) {
                                continue;
                            }
                            $kategori = $subKategoriMap[$subId] ?? 'kinerja';
                            if ($kategori === 'potensial') {
                                $nilaiPot += $hasil;
                            } elseif ($kategori === 'kinerja') {
                                $nilaiKin += $hasil;
                            }
                        }
                    }

                    $result['nilai_potensial'] = round($nilaiPot, 2);
                    $result['nilai_kinerja'] = round($nilaiKin, 2);
                }

                return $result;
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
    public function show(Request $request, string $nip)
    {
        try {
            $withPenilaian = $request->boolean('with_penilaian', false);
            $withRiwayatAsesmen = $request->boolean('with_riwayat_asesmen', false);

            $query = Pegawai::where('nip', $nip)
                ->when($withPenilaian, function ($q) {
                    $q->with('penilaian');
                })
                ->join('jenis_jabatan', 'pegawai.jenis_jabatan_id', '=', 'jenis_jabatan.id')
                ->select('pegawai.*', 'jenis_jabatan.name as jenis_jabatan');

            $pegawai = $query->first();

            if (! $pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pegawai not found',
                ], 404);
            }

            $data = [
                'id' => $pegawai->id,
                'nip' => $pegawai->nip,
                'nama' => $pegawai->name,
                'email' => $pegawai->email,
                'unit_kerja' => $pegawai->unit_organisasi_name,
                'jabatan' => $pegawai->jabatan_name,
                'jenis_jabatan_id' => $pegawai->jenis_jabatan_id,
                'jenis_jabatan' => $pegawai->jenis_jabatan,
                'golongan' => $pegawai->golongan,
                'json' => $pegawai->json,
                'avatar' => $pegawai->avatar,
                'lokasi_kerja' => $pegawai->lokasi_kerja,
                'riwayat_jabatan' => $pegawai->riwayat_jabatan,
                'riwayat_skp' => $pegawai->riwayat_skp,
                'riwayat_pengembangan_kompetensi' => $pegawai->riwayat_pengembangan_kompetensi,
                'riwayat_diklat' => $pegawai->riwayat_diklat,
                'riwayat_sertifikasi' => $pegawai->riwayat_sertifikasi,
                'riwayat_pendidikan' => $pegawai->riwayat_pendidikan,
                'riwayat_umpan_balik' => $pegawai->riwayat_umpan_balik,
                'created_at' => $pegawai->created_at,
                'updated_at' => $pegawai->updated_at,
            ];

            if ($withPenilaian) {
                // Preload subindikator -> kategori map to compute sums
                $subs = \App\Models\SubIndikator::with('indikator')->get();
                $subKategoriMap = [];
                foreach ($subs as $s) {
                    $pid = $s->id;
                    $indikatorPen = strtolower($s->indikator->penilaian ?? '');
                    if (strpos($indikatorPen, 'potensial') !== false || strpos($indikatorPen, 'potensi') !== false) {
                        $subKategoriMap[$pid] = 'potensial';
                    } elseif (strpos($indikatorPen, 'kinerja') !== false) {
                        $subKategoriMap[$pid] = 'kinerja';
                    } else {
                        $subKategoriMap[$pid] = 'tambahan';
                    }
                }

                // Use the same kotak interval logic as recommend()
                $daftarKotak = \App\Models\DaftarKotak::latest()->first();
                $kotakList = $daftarKotak->kotak ?? null;

                $getKotakId = function ($potensial, $kinerja) use ($kotakList) {
                    if (! is_array($kotakList)) {
                        return 0;
                    }
                    foreach ($kotakList as $kotak) {
                        $potMin = isset($kotak['potensialRange']['min']) ? (float) $kotak['potensialRange']['min'] : null;
                        $potMax = isset($kotak['potensialRange']['max']) ? (float) $kotak['potensialRange']['max'] : null;
                        $kinMin = isset($kotak['kinerjaRange']['min']) ? (float) $kotak['kinerjaRange']['min'] : null;
                        $kinMax = isset($kotak['kinerjaRange']['max']) ? (float) $kotak['kinerjaRange']['max'] : null;

                        $potMatch = ($potMin === null || $potensial >= $potMin) && ($potMax === null || $potensial <= $potMax);
                        $kinMatch = ($kinMin === null || $kinerja >= $kinMin) && ($kinMax === null || $kinerja <= $kinMax);

                        if ($potMatch && $kinMatch) {
                            return (int) ($kotak['id'] ?? 0);
                        }
                    }

                    return 0;
                };

                $penObj = $pegawai->penilaian ? $pegawai->penilaian->penilaian : null;
                $nilaiPot = 0.0;
                $nilaiKin = 0.0;

                if (is_array($penObj)) {
                    foreach ($penObj as $subId => $val) {
                        $hasil = null;
                        if (is_array($val) && array_key_exists('hasil', $val)) {
                            $hasil = (float) $val['hasil'];
                        } elseif (is_numeric($val)) {
                            $hasil = (float) $val;
                        }
                        if ($hasil === null) {
                            continue;
                        }
                        $kategori = $subKategoriMap[$subId] ?? 'kinerja';
                        if ($kategori === 'potensial') {
                            $nilaiPot += $hasil;
                        } elseif ($kategori === 'kinerja') {
                            $nilaiKin += $hasil;
                        }
                    }
                }

                $kotakRank = $getKotakId($nilaiPot, $nilaiKin);

                $data['penilaian'] = $penObj;
                $data['nilai_potensial'] = round($nilaiPot, 2);
                $data['nilai_kinerja'] = round($nilaiKin, 2);
                $data['kotak_rank'] = $kotakRank;
            }

            if ($withRiwayatAsesmen) {
                $data['riwayat_asesmen'] = \App\Models\RiwayatAsesmen::query()
                    ->where('pegawai_id', $pegawai->id)
                    ->selectRaw('DISTINCT ON (pegawai_id, nama_asesmen) riwayat_asesmen.*')
                    ->orderBy('pegawai_id')
                    ->orderBy('nama_asesmen')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->get();

                $data['lampiran_asesmen'] = \App\Models\LampiranAsesmen::query()
                    ->where('pegawai_id', $pegawai->id)
                    ->selectRaw('DISTINCT ON (pegawai_id, nama_asesmen) lampiran_asesmen.*')
                    ->orderBy('pegawai_id')
                    ->orderBy('nama_asesmen')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pegawai detail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recommend pegawai based on peta_jabatan_id (returns up to 3 random pegawai)
     */
    public function recommend(string $peta_jabatan_id, Request $request)
    {
        try {
            $retensi = $request->boolean('retensi', false);
            $kategoriJabatan = strtolower(trim((string) $request->get(
                'jenis_jabatan_kategori',
                $request->get('kategori_jabatan', $request->get('jenis_jabatan', 'keduanya'))
            )));

            $allowedTypes = [
                'Jabatan Pimpinan Tinggi Utama',
                'Jabatan Pimpinan Tinggi Madya',
                'Jabatan Pimpinan Tinggi Pratama',
                'Jabatan Administrator',
                'Jabatan Pengawas',
                'Jabatan Pelaksana',
            ];

            // Equivalensi antara jabatan struktural dan fungsional
            $equivalensi = [
                'Jabatan Pimpinan Tinggi Utama' => ['Jabatan Fungsional Ahli Utama'],
                'Jabatan Pimpinan Tinggi Madya' => ['Jabatan Fungsional Ahli Utama'],
                'Jabatan Pimpinan Tinggi Pratama' => ['Jabatan Fungsional Ahli Utama'],
                'Jabatan Administrator' => ['Jabatan Fungsional Ahli Madya'],
                'Jabatan Pengawas' => [
                    'Jabatan Fungsional Ahli Muda',
                    'Jabatan Fungsional Penyelia',
                ],
                'Jabatan Pelaksana' => [
                    'Jabatan Fungsional Ahli Pertama',
                    'Jabatan Fungsional Mahir',
                    'Jabatan Fungsional Terampil',
                    'Jabatan Fungsional Pemula',
                ],
            ];

            $typesToUse = [];
            $mappedType = null;
            $peta = \App\Models\PetaJabatan::find($peta_jabatan_id);
            if ($peta) {
                $probe = strtolower($peta->jenis_jabatan ?? '');
                // check more specific eselon levels first to avoid substring collisions
                if (strpos($probe, 'eselon iv') !== false || strpos($probe, 'pengawas') !== false) {
                    $mappedType = 'Jabatan Pengawas';
                } elseif (strpos($probe, 'eselon iii') !== false || strpos($probe, 'administrator') !== false) {
                    $mappedType = 'Jabatan Administrator';
                } elseif (strpos($probe, 'eselon ii') !== false || strpos($probe, 'jpt pratama') !== false) {
                    $mappedType = 'Jabatan Pimpinan Tinggi Pratama';
                } elseif (strpos($probe, 'eselon i') !== false || strpos($probe, 'jpt madya') !== false) {
                    $mappedType = 'Jabatan Pimpinan Tinggi Utama';
                } elseif (strpos($probe, 'pelaksana') !== false) {
                    $mappedType = 'Jabatan Pelaksana';
                }
            }

            // Load syarat suksesi for this jabatan
            $syaratSuksesi = \App\Models\SyaratSuksesi::where('jabatan_id', $peta_jabatan_id)
                ->orderBy('created_at', 'desc')
                ->first();

            $syaratMap = [];
            if ($syaratSuksesi && is_array($syaratSuksesi->syarat)) {
                $syaratMap = $syaratSuksesi->syarat;
            }

            $gunakanKompetensiTeknis = $syaratSuksesi ? (bool) $syaratSuksesi->gunakan_kompetensi_teknis : false;
            $sesuaiRumpunJabatan = $syaratSuksesi ? (bool) $syaratSuksesi->sesuai_rumpun_jabatan : false;
            $minimalUsia = $syaratSuksesi && $syaratSuksesi->minimal_usia !== null ? (int) $syaratSuksesi->minimal_usia : null;
            $maksimalUsia = $syaratSuksesi && $syaratSuksesi->maksimal_usia !== null ? (int) $syaratSuksesi->maksimal_usia : null;
            $syaratPangkatGolongan = $syaratSuksesi ? $syaratSuksesi->pangkat_golongan : null;
            $requiredGolonganRank = $this->getGolonganRank($syaratPangkatGolongan);

            $allPetaMap = PetaJabatan::all()->keyBy('id')->all();
            $targetRumpun = $this->getRumpunDeputi($peta, $allPetaMap, $retensi);

            if ($retensi) {
                // Retensi: ambil jabatan yang setara (baik struktural maupun fungsional)
                if ($mappedType !== null) {

                    $typesToUse = [$mappedType];

                    if (strpos(strtolower($peta->nama_jabatan), 'deputi') !== false) {
                        $idx = array_search($mappedType, $allowedTypes, true);
                        if ($idx !== false && $idx < count($allowedTypes) - 1) {
                            // Ambil 1 tingkat di bawah
                            $oneLevelBelow = $allowedTypes[$idx + 1];
                            $typesToUse[] = $oneLevelBelow;
                        }
                    }

                    // Tambahkan jabatan fungsional yang setara
                    if (array_key_exists($mappedType, $equivalensi)) {
                        $typesToUse = array_merge($typesToUse, $equivalensi[$mappedType]);
                    }
                }
            } else {
                // Non-retensi: ambil 1 tingkat di bawahnya (baik struktural maupun fungsional)
                if ($mappedType !== null) {
                    $idx = array_search($mappedType, $allowedTypes, true);
                    if ($idx !== false && $idx < count($allowedTypes) - 1) {
                        // Ambil 1 tingkat di bawah
                        $oneLevelBelow = $allowedTypes[$idx + 1];

                        if (strpos(strtolower($peta->nama_jabatan), 'deputi') !== false) {
                            $oneLevelBelow = $allowedTypes[$idx + 2];
                        }

                        $typesToUse = [$oneLevelBelow];
                        // Tambahkan jabatan fungsional yang setara dengan tingkat di bawah
                        if (array_key_exists($oneLevelBelow, $equivalensi)) {
                            $typesToUse = array_merge($typesToUse, $equivalensi[$oneLevelBelow]);
                        }
                    }
                }
            }

            // Filter jenis jabatan berdasarkan pilihan: fungsional, struktural, atau keduanya
            if ($kategoriJabatan === 'fungsional') {
                $typesToUse = array_values(array_filter($typesToUse, function ($type) {
                    return stripos($type, 'fungsional') !== false;
                }));
            } elseif (in_array($kategoriJabatan, ['struktural', 'non_fungsional', 'non-fungsional', 'non fungsional'], true)) {
                $typesToUse = array_values(array_filter($typesToUse, function ($type) {
                    return stripos($type, 'fungsional') === false;
                }));
            }

            // load daftar kotak intervals (if any) to determine kotak position
            $daftarKotak = \App\Models\DaftarKotak::latest()->first();

            // Exclude employees already selected as successor for OTHER positions
            $excludedPegawaiIds = Suksesor::where('peta_jabatan_id', '!=', $peta_jabatan_id)
                ->pluck('pegawai_id')
                ->toArray();

            $pegawaiQuery = Pegawai::with('penilaian')
                ->join('jenis_jabatan', 'pegawai.jenis_jabatan_id', '=', 'jenis_jabatan.id')
                ->whereIn('jenis_jabatan.name', $typesToUse);

            if (! empty($excludedPegawaiIds)) {
                $pegawaiQuery->whereNotIn('pegawai.id', $excludedPegawaiIds);
            }

            $pegawaiCandidates = $pegawaiQuery
                ->select('pegawai.*', 'jenis_jabatan.name as jenis_jabatan')
                ->get();

            $subs = \App\Models\SubIndikator::with('indikator')->get();
            $subKategoriMap = [];

            foreach ($subs as $s) {
                $pid = $s->id;
                $indikatorPen = strtolower($s->indikator->penilaian ?? '');
                if (strpos($indikatorPen, 'potensial') !== false || strpos($indikatorPen, 'potensi') !== false) {
                    $subKategoriMap[$pid] = 'potensial';
                } elseif (strpos($indikatorPen, 'kinerja') !== false) {
                    $subKategoriMap[$pid] = 'kinerja';
                } else {
                    $subKategoriMap[$pid] = 'tambahan';
                }
            }

            // Build subindikator name map for nilai_kompetensi_teknis lookup
            $subNamaMap = [];
            foreach ($subs as $s) {
                $subNamaMap[$s->id] = strtolower($s->subindikator ?? '');
            }

            // Determine weighting based on vacant position type
            $weightMap = [
                'Jabatan Pimpinan Tinggi Madya' => ['total' => 0.8, 'teknis' => 0.2],
                'Jabatan Pimpinan Tinggi Pratama' => ['total' => 0.7, 'teknis' => 0.3],
                'Jabatan Administrator' => ['total' => 0.6, 'teknis' => 0.4],
                'Jabatan Pengawas' => ['total' => 0.5, 'teknis' => 0.5],
            ];
            
            $weights = $mappedType == 'Jabatan Pimpinan Tinggi Utama' ? $weightMap['Jabatan Pimpinan Tinggi Madya'] : $weightMap[$mappedType] ?? null;

            $kotakList = $daftarKotak->kotak ?? null;

            $getKotakId = function ($potensial, $kinerja) use ($kotakList) {
                if (! is_array($kotakList)) {
                    return 0;
                }
                foreach ($kotakList as $kotak) {
                    $potMin = isset($kotak['potensialRange']['min']) ? (float) $kotak['potensialRange']['min'] : null;
                    $potMax = isset($kotak['potensialRange']['max']) ? (float) $kotak['potensialRange']['max'] : null;
                    $kinMin = isset($kotak['kinerjaRange']['min']) ? (float) $kotak['kinerjaRange']['min'] : null;
                    $kinMax = isset($kotak['kinerjaRange']['max']) ? (float) $kotak['kinerjaRange']['max'] : null;

                    $potMatch = ($potMin === null || $potensial >= $potMin) && ($potMax === null || $potensial <= $potMax);
                    $kinMatch = ($kinMin === null || $kinerja >= $kinMin) && ($kinMax === null || $kinerja <= $kinMax);

                    if ($potMatch && $kinMatch) {
                        return (int) ($kotak['id'] ?? 0);
                    }
                }

                return 0;
            };

            $candidates = [];
            foreach ($pegawaiCandidates as $item) {
                // Filter by rumpun jabatan if setting is enabled and target position is under Deputi
                if ($sesuaiRumpunJabatan && $targetRumpun !== null) {
                    $candidateRumpun = $this->getPegawaiRumpunDeputi($item, $allPetaMap);
                    if ($candidateRumpun !== $targetRumpun) {
                        continue;
                    }
                }

                // Filter by Pangkat / Golongan minimal
                if ($requiredGolonganRank > 0) {
                    $pegawaiRank = $this->getGolonganRank($item->golongan);
                    if ($pegawaiRank < $requiredGolonganRank) {
                        continue;
                    }
                }

                // Filter by Usia
                $pegawaiUsia = $this->getUsiaFromNipOrDob($item->nip, $item->json['tglLahir'] ?? null);
                if ($minimalUsia !== null) {
                    if ($pegawaiUsia === null || $pegawaiUsia < $minimalUsia) {
                        continue;
                    }
                }
                if ($maksimalUsia !== null) {
                    if ($pegawaiUsia === null || $pegawaiUsia > $maksimalUsia) {
                        continue;
                    }
                }

                $penObj = $item->penilaian ? $item->penilaian->penilaian : null;

                // Check if pegawai meets syarat suksesi requirements
                $meetsSyarat = true;
                if (! empty($syaratMap)) {
                    if (! is_array($penObj)) {
                        $penObj = [];
                    }

                    foreach ($syaratMap as $subId => $minNilai) {
                        $pegawaiNilai = null;

                        if (array_key_exists($subId, $penObj)) {
                            $val = $penObj[$subId];
                            if (is_array($val) && array_key_exists('nilai', $val)) {
                                $pegawaiNilai = (float) $val['nilai'];
                            } elseif (is_numeric($val)) {
                                $pegawaiNilai = (float) $val;
                            }
                        }

                        // If pegawai doesn't have nilai for this subindikator or nilai is less than minimum
                        if ($pegawaiNilai === null || $pegawaiNilai < (float) $minNilai) {
                            $meetsSyarat = false;
                            break;
                        }
                    }
                }

                // Skip this pegawai if they don't meet syarat suksesi
                if (! $meetsSyarat) {
                    continue;
                }

                $nilaiPot = 0.0;
                $nilaiKin = 0.0;
                if (is_array($penObj)) {
                    foreach ($penObj as $subId => $val) {
                        $hasil = null;
                        if (is_array($val) && array_key_exists('hasil', $val)) {
                            $hasil = (float) $val['hasil'];
                        } elseif (is_numeric($val)) {
                            $hasil = (float) $val;
                        }
                        if ($hasil === null) {
                            continue;
                        }
                        $kategori = $subKategoriMap[$subId] ?? 'kinerja';
                        if ($kategori === 'potensial') {
                            $nilaiPot += $hasil;
                        } elseif ($kategori === 'kinerja') {
                            $nilaiKin += $hasil;
                        }
                    }
                }

                // Extract nilai_kompetensi_teknis
                $nilaiKompetensiTeknis = 0.0;
                if (is_array($penObj)) {
                    foreach ($penObj as $subId => $val) {
                        if (isset($subNamaMap[$subId]) && strpos($subNamaMap[$subId], 'nilai kompetensi teknis') !== false) {
                            $hasil = null;
                            if (is_array($val) && array_key_exists('nilai', $val)) {
                                $hasil = (float) $val['nilai'];
                            } elseif (is_numeric($val)) {
                                $hasil = (float) $val;
                            }
                            if ($hasil !== null) {
                                $nilaiKompetensiTeknis += $hasil;
                            }
                        }
                    }
                }

                // total = average of potensial and kinerja
                $total = ($nilaiPot + $nilaiKin) / 2;

                // nilai_akhir_talenta: use kompetensi teknis weights only if setting is enabled
                $nilaiAkhirTalenta = ($gunakanKompetensiTeknis && $weights)
                    ? round($total * $weights['total'] + $nilaiKompetensiTeknis * $weights['teknis'], 2)
                    : round($total, 2);

                // Determine kotak based on both potensial and kinerja ranges
                $kotakRank = $getKotakId($nilaiPot, $nilaiKin);

                $dob = $item->json['tglLahir'] ?? null;
                $dobTs = $dob ? strtotime($dob) : null;
                if (! $dobTs && $item->nip && strlen(preg_replace('/\D/', '', $item->nip)) >= 8) {
                    $nipClean = preg_replace('/\D/', '', $item->nip);
                    $y = substr($nipClean, 0, 4);
                    $m = substr($nipClean, 4, 2);
                    $d = substr($nipClean, 6, 2);
                    if (checkdate((int) $m, (int) $d, (int) $y)) {
                        $dobTs = strtotime("$y-$m-$d");
                    }
                }

                $candidates[] = [
                    'item' => $item,
                    'penObj' => $penObj,
                    'nilai_pot' => round($nilaiPot, 2),
                    'nilai_kin' => round($nilaiKin, 2),
                    'nilai_kompetensi_teknis' => round($nilaiKompetensiTeknis, 2),
                    'total' => round($total, 2),
                    'nilai_akhir_talenta' => $nilaiAkhirTalenta,
                    'kotak_rank' => $kotakRank,
                    'dob_ts' => $dobTs,
                    'usia' => $pegawaiUsia,
                ];
            }

            usort($candidates, function ($a, $b) {
                // 1) kotak_rank desc
                if ($a['kotak_rank'] !== $b['kotak_rank']) {
                    return $b['kotak_rank'] <=> $a['kotak_rank'];
                }
                // 2) nilai_akhir_talenta desc
                if ($a['nilai_akhir_talenta'] !== $b['nilai_akhir_talenta']) {
                    return $b['nilai_akhir_talenta'] <=> $a['nilai_akhir_talenta'];
                }

                // 3) age: older first -> smaller dob_ts means older
                if ($a['dob_ts'] !== $b['dob_ts']) {
                    if ($a['dob_ts'] === null) {
                        return 1;
                    }
                    if ($b['dob_ts'] === null) {
                        return -1;
                    }

                    return $a['dob_ts'] <=> $b['dob_ts'];
                }

                return 0;
            });

            $top = array_slice($candidates, 0, 3);

            $currentSuksesor = Suksesor::where('peta_jabatan_id', $peta_jabatan_id)->first();
            $currentSuksesorPegawaiId = $currentSuksesor?->pegawai_id;

            $data = array_map(function ($c) use ($currentSuksesorPegawaiId, $gunakanKompetensiTeknis) {
                $item = $c['item'];

                return [
                    'id' => $item->id,
                    'nip' => $item->nip,
                    'nama' => $item->name,
                    'email' => $item->email,
                    'avatar' => $item->avatar,
                    'unit_kerja' => $item->unit_organisasi_name,
                    'jabatan' => $item->jabatan_name,
                    'golongan' => $item->golongan,
                    'jenis_jabatan' => $item->jenis_jabatan,
                    'usia' => $c['usia'],
                    'penilaian' => $c['penObj'],
                    'nilai_potensial' => round($c['nilai_pot'], 2),
                    'nilai_kinerja' => round($c['nilai_kin'], 2),
                    'nilai_kompetensi_teknis' => $c['nilai_kompetensi_teknis'],
                    'nilai_talenta' => $c['total'],
                    'nilai_akhir_talenta' => $c['nilai_akhir_talenta'],
                    'kotak_rank' => $c['kotak_rank'],
                    'is_suksesor' => $currentSuksesorPegawaiId && ($item->id === $currentSuksesorPegawaiId),
                    'gunakan_kompetensi_teknis' => $gunakanKompetensiTeknis,
                ];
            }, $top);

            return response()->json([
                'success' => true,
                'data' => $data,
                'pengaturan' => [
                    'gunakan_kompetensi_teknis' => $gunakanKompetensiTeknis,
                    'sesuai_rumpun_jabatan' => $sesuaiRumpunJabatan,
                    'target_rumpun' => $targetRumpun,
                    'minimal_usia' => $minimalUsia,
                    'maksimal_usia' => $maksimalUsia,
                    'pangkat_golongan' => $syaratPangkatGolongan,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recommendations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determine whether a peta_jabatan is under Deputi Bidang Administrasi or Deputi Bidang Persidangan.
     * Rules:
     * - Sekretaris Jenderal DPD RI is always exempt (returns null for both promosi and rotasi).
     * - Deputi Bidang Administrasi / Deputi Bidang Persidangan:
     *     - Promosi ($isRotasi = false): suksesor must come from within the same Deputi ('administrasi' or 'persidangan').
     *     - Rotasi ($isRotasi = true): exempt across Madya (returns null).
     * - Positions below Deputi: returns 'administrasi' or 'persidangan' for both promosi and rotasi.
     * - Other positions (Inspektorat, Kantor Daerah, etc.): returns null.
     */
    private function getRumpunDeputi(?PetaJabatan $peta, array $allPetaMap, bool $isRotasi = false): ?string
    {
        if (! $peta) {
            return null;
        }

        $namaPeta = strtolower($peta->nama_jabatan ?? '');

        // Sekretaris Jenderal is always exempt (both promosi and rotasi)
        if (strpos($namaPeta, 'sekretaris jenderal') !== false) {
            return null;
        }

        // Deputi positions themselves
        $isDeputiAdm = (strpos($namaPeta, 'deputi bidang administrasi') !== false);
        $isDeputiPers = (strpos($namaPeta, 'deputi bidang persidangan') !== false);

        if ($isDeputiAdm || $isDeputiPers) {
            // Rotasi is exempt for Deputi
            if ($isRotasi) {
                return null;
            }

            // Promosi for Deputi: must come from within the same Deputi rumpun
            return $isDeputiAdm ? 'administrasi' : 'persidangan';
        }

        // For positions below Deputi, traverse up the parent tree to find Eselon I / JPT Madya parent
        $currId = $peta->parent_id;
        while ($currId && isset($allPetaMap[$currId])) {
            $parent = $allPetaMap[$currId];
            $parentNama = strtolower($parent->nama_jabatan ?? '');
            if (strpos($parentNama, 'deputi bidang administrasi') !== false) {
                return 'administrasi';
            }
            if (strpos($parentNama, 'deputi bidang persidangan') !== false) {
                return 'persidangan';
            }
            if (strpos($parentNama, 'sekretaris jenderal') !== false) {
                return null;
            }
            $currId = $parent->parent_id;
        }

        return null;
    }

    /**
     * Determine the rumpun (Deputi) of an employee based on peta_jabatan or unit_organisasi_name.
     */
    private function getPegawaiRumpunDeputi($pegawai, array $allPetaMap): ?string
    {
        // 1. Check direct peta_jabatan_id
        if ($pegawai->peta_jabatan_id && isset($allPetaMap[$pegawai->peta_jabatan_id])) {
            $curr = $allPetaMap[$pegawai->peta_jabatan_id];
            while ($curr) {
                $nama = strtolower($curr->nama_jabatan ?? '');
                if (strpos($nama, 'deputi bidang administrasi') !== false) {
                    return 'administrasi';
                }
                if (strpos($nama, 'deputi bidang persidangan') !== false) {
                    return 'persidangan';
                }
                if (strpos($nama, 'sekretaris jenderal') !== false) {
                    return null;
                }
                $curr = ($curr->parent_id && isset($allPetaMap[$curr->parent_id])) ? $allPetaMap[$curr->parent_id] : null;
            }
        }

        // 2. Fallback: match by unit_organisasi_name
        if ($pegawai->unit_organisasi_name) {
            $unitLower = strtolower(trim($pegawai->unit_organisasi_name));

            // Try exact match on unit_kerja or nama_jabatan first
            foreach ($allPetaMap as $p) {
                $pNama = strtolower(trim($p->nama_jabatan ?? ''));
                $pUnit = strtolower(trim($p->unit_kerja ?? ''));
                if ($pUnit === $unitLower || $pNama === $unitLower) {
                    $curr = $p;
                    while ($curr) {
                        $nama = strtolower($curr->nama_jabatan ?? '');
                        if (strpos($nama, 'deputi bidang administrasi') !== false) {
                            return 'administrasi';
                        }
                        if (strpos($nama, 'deputi bidang persidangan') !== false) {
                            return 'persidangan';
                        }
                        if (strpos($nama, 'sekretaris jenderal') !== false) {
                            return null;
                        }
                        $curr = ($curr->parent_id && isset($allPetaMap[$curr->parent_id])) ? $allPetaMap[$curr->parent_id] : null;
                    }
                }
            }

            // Substring fallback
            foreach ($allPetaMap as $p) {
                $pNama = strtolower(trim($p->nama_jabatan ?? ''));
                $pUnit = strtolower(trim($p->unit_kerja ?? ''));
                if (($pUnit && strpos($unitLower, $pUnit) !== false) || ($pNama && strpos($unitLower, $pNama) !== false)) {
                    $curr = $p;
                    while ($curr) {
                        $nama = strtolower($curr->nama_jabatan ?? '');
                        if (strpos($nama, 'deputi bidang administrasi') !== false) {
                            return 'administrasi';
                        }
                        if (strpos($nama, 'deputi bidang persidangan') !== false) {
                            return 'persidangan';
                        }
                        if (strpos($nama, 'sekretaris jenderal') !== false) {
                            return null;
                        }
                        $curr = ($curr->parent_id && isset($allPetaMap[$curr->parent_id])) ? $allPetaMap[$curr->parent_id] : null;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Calculate age from NIP (first 8 digits: YYYYMMDD) or fallback to tglLahir.
     */
    private function getUsiaFromNipOrDob(?string $nip, ?string $tglLahir = null): ?int
    {
        if ($nip) {
            $nipClean = preg_replace('/\D/', '', $nip);
            if (strlen($nipClean) >= 8) {
                $year = (int) substr($nipClean, 0, 4);
                $month = (int) substr($nipClean, 4, 2);
                $day = (int) substr($nipClean, 6, 2);

                if ($year >= 1900 && $year <= (int) date('Y') && checkdate($month, $day, $year)) {
                    try {
                        return (int) \Carbon\Carbon::create($year, $month, $day)->age;
                    } catch (\Exception $e) {
                    }
                }
            }
        }

        if ($tglLahir) {
            try {
                return (int) \Carbon\Carbon::parse($tglLahir)->age;
            } catch (\Exception $e) {
            }
        }

        return null;
    }

    /**
     * Convert standard civil service golongan (I/a through IV/e) to numerical rank (1-17).
     */
    private function getGolonganRank(?string $golongan): int
    {
        if (! $golongan) {
            return 0;
        }

        $map = [
            'i/a' => 1, 'i/b' => 2, 'i/c' => 3, 'i/d' => 4,
            'ii/a' => 5, 'ii/b' => 6, 'ii/c' => 7, 'ii/d' => 8,
            'iii/a' => 9, 'iii/b' => 10, 'iii/c' => 11, 'iii/d' => 12,
            'iv/a' => 13, 'iv/b' => 14, 'iv/c' => 15, 'iv/d' => 16, 'iv/e' => 17,
        ];

        $clean = strtolower(trim($golongan));

        return $map[$clean] ?? 0;
    }
}
