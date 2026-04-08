<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

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
            $perPage = min(max((int)$perPage, 1), 100);

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

            // Order first by peta_jabatan.kelas_jabatan interpreted as number (desc, NULLS LAST), then by name
            // Use regex to ensure only pure digits are cast to avoid errors on non-numeric values.
            $query->orderByRaw("(CASE WHEN peta_jabatan.kelas_jabatan ~ '^[0-9]+$' THEN CAST(peta_jabatan.kelas_jabatan AS integer) ELSE NULL END) DESC NULLS LAST");

            if (!$withPagination) {
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
                            if ($hasil === null) continue;
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

            if (!$pegawai) {
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
                    if (!is_array($kotakList)) return 0;
                    foreach ($kotakList as $kotak) {
                        $potMin = isset($kotak['potensialRange']['min']) ? (float)$kotak['potensialRange']['min'] : null;
                        $potMax = isset($kotak['potensialRange']['max']) ? (float)$kotak['potensialRange']['max'] : null;
                        $kinMin = isset($kotak['kinerjaRange']['min']) ? (float)$kotak['kinerjaRange']['min'] : null;
                        $kinMax = isset($kotak['kinerjaRange']['max']) ? (float)$kotak['kinerjaRange']['max'] : null;

                        $potMatch = ($potMin === null || $potensial >= $potMin) && ($potMax === null || $potensial <= $potMax);
                        $kinMatch = ($kinMin === null || $kinerja >= $kinMin) && ($kinMax === null || $kinerja <= $kinMax);

                        if ($potMatch && $kinMatch) {
                            return (int)($kotak['id'] ?? 0);
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
                        if ($hasil === null) continue;
                        $kategori = $subKategoriMap[$subId] ?? 'kinerja';
                        if ($kategori === 'potensial') {
                            $nilaiPot += $hasil;
                        } else if ($kategori === 'kinerja') {
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
    public function recommend(String $peta_jabatan_id, Request $request)
    {
        try {
            $retensi = $request->boolean('retensi', false);
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
                'Jabatan Pimpinan Tinggi Utama' => [],
                'Jabatan Pimpinan Tinggi Madya' => [],
                'Jabatan Pimpinan Tinggi Pratama' => ['Jabatan Fungsional Ahli Utama'],
                'Jabatan Administrator' => ['Jabatan Fungsional Ahli Madya'],
                'Jabatan Pengawas' => ['Jabatan Fungsional Ahli Muda'],
                'Jabatan Pelaksana' => ['Jabatan Fungsional Ahli Pertama'],
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

            if ($retensi) {
                // Retensi: ambil jabatan yang setara (baik struktural maupun fungsional)
                if ($mappedType !== null) {
                    $typesToUse = [$mappedType];
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
                        $typesToUse = [$oneLevelBelow];
                        // Tambahkan jabatan fungsional yang setara dengan tingkat di bawah
                        if (array_key_exists($oneLevelBelow, $equivalensi)) {
                            $typesToUse = array_merge($typesToUse, $equivalensi[$oneLevelBelow]);
                        }
                    }
                }
            }

            // load daftar kotak intervals (if any) to determine kotak position
            $daftarKotak = \App\Models\DaftarKotak::latest()->first();

            $pegawaiCandidates = Pegawai::with('penilaian')
                ->join('jenis_jabatan', 'pegawai.jenis_jabatan_id', '=', 'jenis_jabatan.id')
                ->whereIn('jenis_jabatan.name', $typesToUse)
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
                'Jabatan Pimpinan Tinggi Madya'   => ['total' => 0.8, 'teknis' => 0.2],
                'Jabatan Pimpinan Tinggi Pratama' => ['total' => 0.7, 'teknis' => 0.3],
                'Jabatan Administrator'           => ['total' => 0.6, 'teknis' => 0.4],
                'Jabatan Pengawas'                => ['total' => 0.5, 'teknis' => 0.5],
            ];
            $weights = $weightMap[$mappedType] ?? null;

            $kotakList = $daftarKotak->kotak ?? null;

            $getKotakId = function ($potensial, $kinerja) use ($kotakList) {
                if (!is_array($kotakList)) return 0;
                foreach ($kotakList as $kotak) {
                    $potMin = isset($kotak['potensialRange']['min']) ? (float)$kotak['potensialRange']['min'] : null;
                    $potMax = isset($kotak['potensialRange']['max']) ? (float)$kotak['potensialRange']['max'] : null;
                    $kinMin = isset($kotak['kinerjaRange']['min']) ? (float)$kotak['kinerjaRange']['min'] : null;
                    $kinMax = isset($kotak['kinerjaRange']['max']) ? (float)$kotak['kinerjaRange']['max'] : null;

                    $potMatch = ($potMin === null || $potensial >= $potMin) && ($potMax === null || $potensial <= $potMax);
                    $kinMatch = ($kinMin === null || $kinerja >= $kinMin) && ($kinMax === null || $kinerja <= $kinMax);

                    if ($potMatch && $kinMatch) {
                        return (int)($kotak['id'] ?? 0);
                    }
                }
                return 0;
            };

            $candidates = [];
            foreach ($pegawaiCandidates as $item) {
                $penObj = $item->penilaian ? $item->penilaian->penilaian : null;

                // Check if pegawai meets syarat suksesi requirements
                $meetsSyarat = true;
                if (!empty($syaratMap)) {
                    if (!is_array($penObj)) {
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
                if (!$meetsSyarat) {
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
                        if ($hasil === null) continue;
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
                            if (is_array($val) && array_key_exists('hasil', $val)) {
                                $hasil = (float) $val['hasil'];
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

                // nilai_akhir_talenta with weighting based on vacant position type
                $nilaiAkhirTalenta = $weights
                    ? round($total * $weights['total'] + $nilaiKompetensiTeknis * $weights['teknis'], 2)
                    : round($total, 2);

                // Determine kotak based on both potensial and kinerja ranges
                $kotakRank = $getKotakId($nilaiPot, $nilaiKin);

                $dob = $item->json['tglLahir'] ?? null;
                $dobTs = $dob ? strtotime($dob) : null;

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
                    if ($a['dob_ts'] === null) return 1;
                    if ($b['dob_ts'] === null) return -1;
                    return $a['dob_ts'] <=> $b['dob_ts'];
                }
                return 0;
            });

            $top = array_slice($candidates, 0, 3);

            $data = array_map(function ($c) {
                $item = $c['item'];
                return [
                    'nip' => $item->nip,
                    'nama' => $item->name,
                    'email' => $item->email,
                    'avatar' => $item->avatar,
                    'unit_kerja' => $item->unit_organisasi_name,
                    'jabatan' => $item->jabatan_name,
                    'golongan' => $item->golongan,
                    'jenis_jabatan' => $item->jenis_jabatan,
                    'penilaian' => $c['penObj'],
                    'nilai_potensial' => round($c['nilai_pot'], 2),
                    'nilai_kinerja' => round($c['nilai_kin'], 2),
                    'nilai_kompetensi_teknis' => $c['nilai_kompetensi_teknis'],
                    'nilai_talenta' => $c['total'],
                    'nilai_akhir_talenta' => $c['nilai_akhir_talenta'],
                    'kotak_rank' => $c['kotak_rank'],
                ];
            }, $top);

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recommendations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
