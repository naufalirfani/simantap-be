<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\StandarKompetensiMsk;
use App\Models\SubIndikator;
use Illuminate\Http\Request;

class PengembanganStatistikController extends Controller
{
    /**
     * GET /api/pengembangan/statistik
     *
     * Returns comprehensive development statistics for MSK competency and
     * talent potential, including employee drill-down lists per category.
     *
     * Query params (all optional):
     *   unit_organisasi_name  – filter by pegawai.unit_organisasi_name
     *   jabatan_name          – filter by pegawai.jabatan_name
     *   jenis_jabatan         – filter by pegawai.jenis_jabatan
     */
    public function index(Request $request)
    {
        try {
            $filters = array_filter([
                'unit_organisasi_name' => $request->get('unit_organisasi_name'),
                'jabatan_name'         => $request->get('jabatan_name'),
                'jenis_jabatan'        => $request->get('jenis_jabatan'),
            ]);

            $data = $this->buildStatistik($filters);

            return response()->json(['success' => true, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pengembangan statistik',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Core builder
    // -------------------------------------------------------------------------

    private function buildStatistik(array $filters): array
    {
        // ------------------------------------------------------------------
        // 1. Load subindikator metadata
        // ------------------------------------------------------------------
        $allSubIndikators = SubIndikator::with('indikator')->get();

        $mskSubIds     = [];
        $potensiSubIds = [];
        $mskSubLabels  = [];   // id => label string
        $potensiSubLabels = [];

        foreach ($allSubIndikators as $sub) {
            $indNama = strtolower($sub->indikator->indikator ?? '');
            $isMsk = str_contains($indNama, 'manajerial') ||
                str_contains($indNama, 'sosial kultural') ||
                str_contains($indNama, 'msk');
            $isPotensi = str_contains($indNama, 'potensi talenta');

            if ($isMsk) {
                $mskSubIds[] = $sub->id;
                $mskSubLabels[$sub->id] = $sub->subindikator ?? $sub->id;
            }
            if ($isPotensi) {
                $potensiSubIds[] = $sub->id;
                $potensiSubLabels[$sub->id] = $sub->subindikator ?? $sub->id;
            }
        }
        unset($allSubIndikators);

        // ------------------------------------------------------------------
        // 2. Load standar MSK: [jenis_jabatan_id][subindikator_id] => standar
        // ------------------------------------------------------------------
        $standarMap = [];
        foreach (StandarKompetensiMsk::all() as $st) {
            $standarMap[$st->jenis_jabatan_id][$st->subindikator_id] = (float) $st->standar;
        }

        // ------------------------------------------------------------------
        // 3. Build base query (no eager load of full penilaian yet)
        //    We select only the columns we need from pegawai, then chunk.
        // ------------------------------------------------------------------
        $baseQuery = Pegawai::query()
            ->select([
                'pegawai.id',
                'pegawai.nip',
                'pegawai.name',
                'pegawai.jabatan_name',
                'pegawai.unit_organisasi_name',
                'pegawai.jenis_jabatan_id',
                'pegawai.avatar',
            ]);

        if (!empty($filters['unit_organisasi_name'])) {
            $baseQuery->where('pegawai.unit_organisasi_name', 'like', '%' . $filters['unit_organisasi_name'] . '%');
        }
        if (!empty($filters['jabatan_name'])) {
            $baseQuery->where('pegawai.jabatan_name', 'like', '%' . $filters['jabatan_name'] . '%');
        }
        if (!empty($filters['jenis_jabatan'])) {
            $baseQuery->join('jenis_jabatan', 'pegawai.jenis_jabatan_id', '=', 'jenis_jabatan.id')
                ->where('jenis_jabatan.name', 'like', '%' . $filters['jenis_jabatan'] . '%');
        }

        $totalPegawai = (clone $baseQuery)->count();

        // ------------------------------------------------------------------
        // 4. Classify using NIP buckets (store only NIP strings, not objects)
        //    We resolve the employee info map at the end.
        // ------------------------------------------------------------------

        // NIP-keyed info map built incrementally
        $empInfoMap = [];   // nip => [name, nip, jabatan, unitKerja]

        $sudahNips  = [];
        $belumNips  = [];
        $mskMemenuhiNips = [];
        $mskDibawahNips  = [];
        $potensiTinggiNips = [];
        $potensiSedangNips = [];
        $potensiRendahNips = [];

        // per-subindikator NIP buckets
        $perSubMskNips    = [];   // [sid][bucket] => nip[]
        $perSubPotensiNips = [];
        foreach ($mskSubIds as $sid) {
            $perSubMskNips[$sid] = ['memenuhi_standar' => [], 'di_bawah_standar' => [], 'belum_dinilai' => []];
        }
        foreach ($potensiSubIds as $sid) {
            $perSubPotensiNips[$sid] = ['tinggi' => [], 'sedang' => [], 'rendah' => [], 'belum_dinilai' => []];
        }

        $sumNilaiMsk     = 0.0;
        $sumNilaiPotensi = 0.0;
        $countMsk        = 0;
        $countPotensi    = 0;

        (clone $baseQuery)->with(['penilaian' => function ($q) {
            $q->select(['id', 'pegawai_id', 'penilaian']);
        }])->chunk(150, function ($chunk) use (
            &$empInfoMap,
            &$sudahNips,
            &$belumNips,
            &$mskMemenuhiNips,
            &$mskDibawahNips,
            &$potensiTinggiNips,
            &$potensiSedangNips,
            &$potensiRendahNips,
            &$perSubMskNips,
            &$perSubPotensiNips,
            &$sumNilaiMsk,
            &$sumNilaiPotensi,
            &$countMsk,
            &$countPotensi,
            $mskSubIds,
            $potensiSubIds,
            $standarMap
        ) {
            foreach ($chunk as $pegawai) {
                $nip = $pegawai->nip;

                /** @var \App\Models\Penilaian|null $penilaianRec */
                $penilaianRec  = $pegawai->penilaian;
                $penilaianData = ($penilaianRec && is_array($penilaianRec->penilaian))
                    ? $penilaianRec->penilaian
                    : null;

                $empInfoMap[$nip] = [
                    'name'      => $pegawai->name,
                    'nip'       => $nip,
                    'jenis_jabatan_id' => $pegawai->jenis_jabatan_id,
                    'jabatan'   => $pegawai->jabatan_name,
                    'unitKerja' => $pegawai->unit_organisasi_name,
                    'avatar'    => $pegawai->avatar,
                    'penilaian' => $penilaianData,
                ];

                if ($penilaianData === null) {
                    $belumNips[] = $nip;
                    foreach ($mskSubIds as $sid) {
                        $perSubMskNips[$sid]['belum_dinilai'][] = $nip;
                    }
                    foreach ($potensiSubIds as $sid) {
                        $perSubPotensiNips[$sid]['belum_dinilai'][] = $nip;
                    }
                    continue;
                }

                $sudahNips[] = $nip;
                $jid = $pegawai->jenis_jabatan_id;

                // --- MSK ---
                $mskDibawahFlag = false;
                $nilaiMskList   = [];

                foreach ($mskSubIds as $sid) {
                    $entry  = $penilaianData[$sid] ?? null;
                    $nilai  = $entry !== null
                        ? (float) (is_array($entry) ? ($entry['nilai'] ?? 0) : $entry)
                        : 0.0;
                    $standar = ($jid && isset($standarMap[$jid][$sid]))
                        ? (float) $standarMap[$jid][$sid]
                        : null;

                    $nilaiMskList[] = $nilai;

                    if ($standar !== null) {
                        if ($nilai >= $standar) {
                            $perSubMskNips[$sid]['memenuhi_standar'][] = $nip;
                        } else {
                            $perSubMskNips[$sid]['di_bawah_standar'][] = $nip;
                            $mskDibawahFlag = true;
                        }
                    }
                }

                $hasAnyStandar = $jid && !empty($standarMap[$jid] ?? []);
                if ($hasAnyStandar) {
                    if ($mskDibawahFlag) {
                        $mskDibawahNips[] = $nip;
                    } else {
                        $mskMemenuhiNips[] = $nip;
                    }
                } else {
                    $mskMemenuhiNips[] = $nip;
                }

                if ($nilaiMskList) {
                    $sumNilaiMsk += array_sum($nilaiMskList) / count($nilaiMskList);
                    $countMsk++;
                }

                // --- Potensi ---
                $nilaiPotensiList = [];
                foreach ($potensiSubIds as $sid) {
                    $entry = $penilaianData[$sid] ?? null;
                    $nilai = $entry !== null
                        ? (float) (is_array($entry) ? ($entry['nilai'] ?? 0) : $entry)
                        : 0.0;
                    $nilaiPotensiList[] = $nilai;

                    if ($nilai >= 4) {
                        $perSubPotensiNips[$sid]['tinggi'][] = $nip;
                    } elseif ($nilai >= 2) {
                        $perSubPotensiNips[$sid]['sedang'][] = $nip;
                    } else {
                        $perSubPotensiNips[$sid]['rendah'][] = $nip;
                    }
                }

                if ($nilaiPotensiList) {
                    $avgPotensi = array_sum($nilaiPotensiList) / count($nilaiPotensiList);
                    $sumNilaiPotensi += $avgPotensi;
                    $countPotensi++;

                    if ($avgPotensi >= 4) {
                        $potensiTinggiNips[] = $nip;
                    } elseif ($avgPotensi >= 2) {
                        $potensiSedangNips[] = $nip;
                    } else {
                        $potensiRendahNips[] = $nip;
                    }
                }
            }
        });

        // ------------------------------------------------------------------
        // 5. Perlu Pengembangan = di_bawah_standar MSK OR potensi rendah
        // ------------------------------------------------------------------
        $perluNips = array_values(array_unique(array_merge($mskDibawahNips, $potensiRendahNips)));

        // ------------------------------------------------------------------
        // 6. Averages
        // ------------------------------------------------------------------
        $rataRataMsk     = $countMsk     > 0 ? round($sumNilaiMsk     / $countMsk, 2)     : 0;
        $rataRataPotensi = $countPotensi > 0 ? round($sumNilaiPotensi / $countPotensi, 2) : 0;

        // ------------------------------------------------------------------
        // 7. Build per-subindikator result arrays (resolve NIPs -> info)
        // ------------------------------------------------------------------
        $perSubMskResult = [];
        foreach ($mskSubIds as $sid) {
            if (!isset($perSubMskNips[$sid])) continue;
            $perSubMskResult[] = [
                'id'    => $sid,
                'label' => $mskSubLabels[$sid] ?? $sid,
                'memenuhi_standar' => $this->bucketNips($perSubMskNips[$sid]['memenuhi_standar'], $empInfoMap),
                'di_bawah_standar' => $this->bucketNips($perSubMskNips[$sid]['di_bawah_standar'], $empInfoMap),
                'belum_dinilai'    => $this->bucketNips($perSubMskNips[$sid]['belum_dinilai'], $empInfoMap),
            ];
        }

        $perSubPotensiResult = [];
        foreach ($potensiSubIds as $sid) {
            if (!isset($perSubPotensiNips[$sid])) continue;
            $perSubPotensiResult[] = [
                'id'    => $sid,
                'label' => $potensiSubLabels[$sid] ?? $sid,
                'tinggi'        => $this->bucketNips($perSubPotensiNips[$sid]['tinggi'], $empInfoMap),
                'sedang'        => $this->bucketNips($perSubPotensiNips[$sid]['sedang'], $empInfoMap),
                'rendah'        => $this->bucketNips($perSubPotensiNips[$sid]['rendah'], $empInfoMap),
                'belum_dinilai' => $this->bucketNips($perSubPotensiNips[$sid]['belum_dinilai'], $empInfoMap),
            ];
        }

        return [
            'total_pegawai'      => $totalPegawai,
            'sudah_dinilai'      => $this->bucketNips($sudahNips, $empInfoMap),
            'belum_dinilai'      => $this->bucketNips($belumNips, $empInfoMap),
            'rata_rata_kompetensi' => $rataRataMsk,
            'rata_rata_potensi'    => $rataRataPotensi,
            'perlu_pengembangan' => $this->bucketNips($perluNips, $empInfoMap),
            'kategori_kompetensi' => [
                'memenuhi_standar' => $this->bucketNips($mskMemenuhiNips, $empInfoMap),
                'di_bawah_standar' => $this->bucketNips($mskDibawahNips, $empInfoMap),
            ],
            'kategori_potensi' => [
                'tinggi' => $this->bucketNips($potensiTinggiNips, $empInfoMap),
                'sedang' => $this->bucketNips($potensiSedangNips, $empInfoMap),
                'rendah' => $this->bucketNips($potensiRendahNips, $empInfoMap),
            ],
            'per_subindikator_kompetensi' => $perSubMskResult,
            'per_subindikator_potensi'    => $perSubPotensiResult,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Given an array of NIPs and the full employee info map,
     * return {count, employees} resolving each NIP to its info object.
     * Missing NIPs are silently skipped.
     */
    private function bucketNips(array $nips, array &$empInfoMap): array
    {
        $employees = [];
        foreach ($nips as $nip) {
            if (isset($empInfoMap[$nip])) {
                $employees[] = $empInfoMap[$nip];
            }
        }
        return [
            'count'     => count($employees),
            'employees' => $employees,
        ];
    }
}
