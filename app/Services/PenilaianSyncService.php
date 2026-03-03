<?php

namespace App\Services;

use App\Models\Instrumen;
use App\Models\Pegawai;
use App\Models\Penilaian;
use App\Models\StandarKompetensiMsk;
use App\Models\SubIndikator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PenilaianSyncService
{
    // ─────────────────────────────────────────────────────────────
    // String helpers
    // ─────────────────────────────────────────────────────────────

    public function normalizeName(?string $s): string
    {
        if ($s === null) {
            return '';
        }
        $s = trim(mb_strtolower($s));
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($trans !== false) {
            $s = $trans;
        }
        $s = preg_replace('/[^a-z0-9]+/i', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    // ─────────────────────────────────────────────────────────────
    // Hasil computation
    // ─────────────────────────────────────────────────────────────

    /**
     * Compute the weighted "hasil" score for a subindikator.
     *
     * @param  float      $nilai
     * @param  float      $bobot           percentage weight (0-100)
     * @param  bool       $usesStandarMsk  divide by standar before weighting
     * @param  bool       $usesStandarPotensi divide by 5 before weighting
     * @param  float|null $standar         required when $usesStandarMsk is true
     */
    public function computeHasil(
        float $nilai,
        float $bobot,
        bool $usesStandarMsk,
        bool $usesStandarPotensi,
        ?float $standar = null
    ): float {
        if ($usesStandarMsk) {
            $standar = $standar ?? 0.0;
            return $standar > 0 ? ($nilai / $standar) * 100.0 * ($bobot / 100.0) : 0.0;
        }

        if ($usesStandarPotensi) {
            return ($nilai / 5) * 100.0 * ($bobot / 100.0);
        }

        return $nilai * ($bobot / 100.0);
    }

    // ─────────────────────────────────────────────────────────────
    // Tingkat Pendidikan Formal
    // ─────────────────────────────────────────────────────────────

    /**
     * Derive the nilai for "Tingkat Pendidikan Formal" from pegawai JSON
     * by matching the employee's education level against instrumen scoring rules.
     *
     * @param  mixed $pegawaiJson raw value from Pegawai::$json
     * @param  \Illuminate\Support\Collection $instrumens
     */
    public function getNilaiTingkatPendidikanFormal($pegawaiJson, $instrumens): ?float
    {
        $edu = data_get($pegawaiJson, 'tkPendidikanTerakhir')
            ?? data_get($pegawaiJson, 'tk_pendidikan_terakhir');

        $normEdu = '';
        if (!empty($edu)) {
            $normEdu = strtolower(trim((string) $edu));
            $normEdu = preg_replace('/[\.\-\s]+/', '', $normEdu);
        }

        // Detect canonical education level
        $level = null;
        if ($normEdu !== '') {
            if (preg_match('/s3|strata3|strataiii/', $normEdu)) {
                $level = 'S3';
            } elseif (preg_match('/s2|strata2|strataii/', $normEdu)) {
                $level = 'S2';
            } elseif (preg_match('/s1|d4|div|d\.4|strata1|stratai/', $normEdu)) {
                $level = 'S1/D4';
            } elseif (preg_match('/d3|diii/', $normEdu)) {
                $level = 'D3';
            } elseif (preg_match('/slta|sma|smk|ma|sekolahlanjutantingkatatas/', $normEdu)) {
                $level = 'SLTA';
            } else {
                // digit fallback
                if (strpos($normEdu, '3') !== false) $level = 'S3';
                elseif (strpos($normEdu, '2') !== false) $level = 'S2';
                elseif (strpos($normEdu, '1') !== false || strpos($normEdu, 'd4') !== false) $level = 'S1/D4';
            }
        }

        // Map instrumen texts to skor values
        $mapping = [];
        foreach ($instrumens as $ins) {
            $text = strtolower($ins->instrumen ?? '');
            $skor = (float) $ins->skor;
            $t    = preg_replace('/[\.\-\s]+/', '', $text);

            if (stripos($t, 's3') !== false || stripos($text, 'strata 3') !== false) {
                $mapping['S3'] = $skor;
            } elseif (stripos($t, 's2') !== false || stripos($text, 'strata 2') !== false) {
                $mapping['S2'] = $skor;
            } elseif (stripos($t, 's1') !== false || stripos($t, 'd4') !== false || stripos($text, 'strata 1') !== false) {
                $mapping['S1/D4'] = $skor;
            } elseif (stripos($t, 'd3') !== false || stripos($text, 'diploma iii') !== false) {
                $mapping['D3'] = $skor;
            } elseif (stripos($t, 'slta') !== false || stripos($text, 'sekolah lanjutan') !== false || stripos($text, 'sma') !== false) {
                $mapping['SLTA'] = $skor;
            }
        }

        if ($level && array_key_exists($level, $mapping)) {
            return $mapping[$level];
        }

        // Direct text-match fallback
        foreach ($instrumens as $ins) {
            $text = strtolower($ins->instrumen ?? '');
            if ($normEdu !== '' && stripos(preg_replace('/[\.\-\s]+/', '', $text), $normEdu) !== false) {
                return (float) $ins->skor;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // SKP (Penilaian Kerja) – external API
    // ─────────────────────────────────────────────────────────────

    /**
     * Fetch the kuadranKinerja string for the latest SKP year for a single NIP.
     *
     * Endpoint:  GET {SKP_API_BASE_URL}/{nip}
     * Env vars:  SKP_API_BASE_URL, SKP_API_TOKEN
     *
     * Expected response shape:
     * {
     *   "data": {
     *     "data": [
     *       { "kuadranKinerja": "BAIK", "tahun": "2024", ... },
     *       { "kuadranKinerja": "BAIK", "tahun": "2023", ... }
     *     ]
     *   }
     * }
     *
     * Strategy: pick the record with the highest "tahun", return its "kuadranKinerja".
     * Returns null when the API call fails or the field is absent.
     */
    public function fetchKuadranKinerjaSKP(string $nip): ?string
    {
        $baseUrl = rtrim(env('OKK_API_BASE_URL', 'https://okk.dpd.go.id/dpd-portal/openapi/talenta/rw'), '/');
        $token   = env('OKK_API_TOKEN', '');

        try {
            $response = Http::withHeaders([
                'app-token'    => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get("{$baseUrl}/rw-skp22/{$nip}");

            if (!$response->successful()) {
                Log::warning("SKP API non-success for NIP {$nip}", ['status' => $response->status()]);
                return null;
            }

            $body    = $response->json();
            $records = data_get($body, 'data.data');

            if (is_array($records) && count($records) > 0) {
                // Sort descending by tahun, pick latest
                usort($records, fn ($a, $b) => (int) ($b['tahun'] ?? 0) - (int) ($a['tahun'] ?? 0));
                $latest = $records[0];

                if (!empty($latest['kuadranKinerja'])) {
                    return (string) $latest['kuadranKinerja'];
                }
            }

            Log::warning("SKP API: kuadranKinerja not found for NIP {$nip}", ['body' => $body]);
            return null;
        } catch (\Exception $e) {
            Log::error("SKP API error for NIP {$nip}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Derive the nilai for a "Penilaian Kerja (SKP)" subindikator by matching
     * the kuadranKinerja string against the subindikator's instrumen entries.
     *
     * Instrumen entries look like:
     *   "a. Sangat Baik", "b. Baik", "c. Butuh Perbaikan", "d. Kurang", "e. Sangat Kurang"
     *
     * The API field kuadranKinerja contains values like:
     *   "SANGAT BAIK", "BAIK", "BUTUH PERBAIKAN", "KURANG", "SANGAT KURANG"
     *
     * Matching is done case-insensitively by checking if the instrumen text contains
     * the normalised kuadranKinerja (or vice-versa).
     *
     * @param  string|null $kuadranKinerja  value from fetchKuadranKinerjaSKP()
     * @param  \Illuminate\Support\Collection $instrumens
     */
    public function getNilaiSKPFromKuadran(?string $kuadranKinerja, $instrumens): ?float
    {
        if ($kuadranKinerja === null || $instrumens->isEmpty()) {
            return null;
        }

        // Normalize: lower-case, strip punctuation/extra spaces
        $normKuadran = strtolower(trim($kuadranKinerja));
        $normKuadran = preg_replace('/[^a-z0-9\s]+/', '', $normKuadran);
        $normKuadran = preg_replace('/\s+/', ' ', $normKuadran);

        // Pre-normalise all instrumen entries once
        $normalized = $instrumens->map(function ($ins) {
            $instrText = strtolower($ins->instrumen ?? '');
            $instrNorm = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]+/', '', $instrText)));
            // Strip leading "a. ", "b. " etc.
            $instrCore = preg_replace('/^[a-z]\s+/', '', $instrNorm);
            return ['ins' => $ins, 'norm' => $instrNorm, 'core' => $instrCore];
        });

        // Pass 1: exact match (prevents "baik" hitting "sangat baik")
        foreach ($normalized as $item) {
            if ($item['core'] === $normKuadran) {
                return (float) $item['ins']->skor;
            }
        }

        // Pass 2: substring fallback
        foreach ($normalized as $item) {
            if (str_contains($item['norm'], $normKuadran)) {
                return (float) $item['ins']->skor;
            }
        }

        Log::warning("SKP getNilaiSKPFromKuadran: no instrumen match for kuadranKinerja='{$kuadranKinerja}'");
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Integritas / Moralitas – external API (hukdis)
    // ─────────────────────────────────────────────────────────────

    /**
     * Fetch riwayat hukuman disiplin for a single NIP.
     *
     * Endpoint:  GET {HUKDIS_API_BASE_URL}/rw-hukdis/{nip}
     * Env vars:  HUKDIS_API_BASE_URL  (defaults to same base as SKP)
     *            HUKDIS_API_TOKEN     (falls back to SKP_API_TOKEN)
     *
     * Returns the raw array of hukuman records, or null on failure.
     */
    public function fetchHukumanDisiplin(string $nip): ?array
    {
        $baseUrl = rtrim(env('OKK_API_BASE_URL', 'https://okk.dpd.go.id/dpd-portal/openapi/talenta/rw'), '/');
        $token   = env('OKK_API_TOKEN', '');

        try {
            $response = Http::withHeaders([
                'app-token'    => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get("{$baseUrl}/rw-hukdis/{$nip}");

            if (!$response->successful()) {
                Log::warning("HukDis API non-success for NIP {$nip}", ['status' => $response->status()]);
                return null;
            }

            $body    = $response->json();
            $records = data_get($body, 'data.data');

            return is_array($records) ? $records : [];
        } catch (\Exception $e) {
            Log::error("HukDis API error for NIP {$nip}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Derive the nilai for an "Integritas/Moralitas" subindikator by analysing
     * the riwayat hukuman disiplin records against instrumen scoring rules.
     *
     * Decision logic (priority: highest severity wins):
     *  e – Sedang menjalani hukuman disiplin      → today < akhirHukumTanggal
     *  d – Pernah dijatuhi hukuman disiplin berat  → jenisTingkatHukumanId = 'B', akhir within 5 yrs
     *  c – Pernah dijatuhi hukuman disiplin sedang → jenisTingkatHukumanId = 'S', akhir within 5 yrs
     *  b – Pernah dijatuhi hukuman disiplin ringan → jenisTingkatHukumanId = 'R', akhir within 5 yrs
     *  a – Tidak pernah dijatuhi hukuman disiplin  → none of the above
     *
     * Date format from API: "DD-MM-YYYY". When akhirHukumTanggal is absent it is
     * derived from hukumanTanggal + masaTahun (years) + masaBulan (months).
     *
     * @param  array|null $hukumanRecords   raw records from fetchHukumanDisiplin()
     * @param  \Illuminate\Support\Collection $instrumens
     */
    public function getNilaiIntegritasMoralitas(?array $hukumanRecords, $instrumens): ?float
    {
        if ($instrumens->isEmpty()) {
            return null;
        }

        $today      = now()->startOfDay();
        $fiveYrsAgo = now()->subYears(5)->startOfDay();

        /** Parse "DD-MM-YYYY" → Carbon or null */
        $parseDate = static function (?string $d): ?\Carbon\Carbon {
            if (!$d) {
                return null;
            }
            try {
                return \Carbon\Carbon::createFromFormat('d-m-Y', trim($d))->startOfDay();
            } catch (\Exception $e) {
                return null;
            }
        };

        $sedangMenjalani = false;
        $hasBerat        = false;
        $hasSedang       = false;
        $hasRingan       = false;

        if (is_array($hukumanRecords)) {
            foreach ($hukumanRecords as $record) {
                // Resolve akhir date; fall back to hukumanTanggal + masa
                $akhirDate = $parseDate($record['akhirHukumTanggal'] ?? null);
                if ($akhirDate === null) {
                    $mulaiDate = $parseDate($record['hukumanTanggal'] ?? null);
                    if ($mulaiDate !== null) {
                        $tahun     = (int) ($record['masaTahun'] ?? 0);
                        $bulan     = (int) ($record['masaBulan'] ?? 0);
                        $akhirDate = $mulaiDate->copy()->addYears($tahun)->addMonths($bulan);
                    }
                }

                if ($akhirDate === null) {
                    continue;
                }

                // Still undergoing? (akhir >= today)
                if ($akhirDate->greaterThanOrEqualTo($today)) {
                    $sedangMenjalani = true;
                }

                // Within 5-year window? (akhir > 5 years ago)
                if ($akhirDate->greaterThan($fiveYrsAgo)) {
                    $tingkat = strtoupper(trim((string) ($record['jenisTingkatHukumanId'] ?? '')));
                    if ($tingkat === 'B') {
                        $hasBerat = true;
                    } elseif ($tingkat === 'S') {
                        $hasSedang = true;
                    } elseif ($tingkat === 'R') {
                        $hasRingan = true;
                    }
                }
            }
        }

        // Determine category by severity (highest priority wins)
        if ($sedangMenjalani) {
            $category = 'sedang menjalani';
        } elseif ($hasBerat) {
            $category = 'berat';
        } elseif ($hasSedang) {
            $category = 'sedang';
        } elseif ($hasRingan) {
            $category = 'ringan';
        } else {
            $category = 'tidak pernah';
        }

        // Match against instrumen text (strip leading "a. " prefix before checking)
        foreach ($instrumens as $ins) {
            $text = strtolower($ins->instrumen ?? '');
            // Remove leading bullet like "a. ", "b. ", etc.
            $core = trim(preg_replace('/^[a-z][.\)]\s*/', '', $text));

            $matched = match ($category) {
                'sedang menjalani' => str_contains($core, 'sedang menjalani'),
                'berat'            => str_contains($core, 'berat') && !str_contains($core, 'menjalani'),
                'sedang'           => str_contains($core, 'sedang') && !str_contains($core, 'menjalani') && str_contains($core, 'disiplin'),
                'ringan'           => str_contains($core, 'ringan'),
                'tidak pernah'     => str_contains($core, 'tidak pernah'),
                default            => false,
            };

            if ($matched) {
                return (float) $ins->skor;
            }
        }

        Log::warning("IntegritasMoralitas: no instrumen match for category='{$category}'");
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Main sync
    // ─────────────────────────────────────────────────────────────

    /**
     * Recalculate and upsert penilaian records for all (or specific) pegawai.
     *
     * @param  array|null $filterNips  When provided, only pegawai with these NIPs are processed.
     * @return array{updated: int, errors: array}
     */
    public function syncPenilaian(?array $filterNips = null): array
    {
        set_time_limit(600);

        $allSub     = SubIndikator::with('indikator')->get()->keyBy('id');
        $instrBySub = Instrumen::all()->groupBy('subindikator_id');

        // Build standar map [jenis_jabatan_id][subindikator_id] => standar
        $standarMap = [];
        foreach (StandarKompetensiMsk::all() as $st) {
            $standarMap[$st->jenis_jabatan_id][$st->subindikator_id] = (float) $st->standar;
        }

        // Classify subindikator ids
        $masaIds        = [];
        $skpIds         = [];
        $kualifikasiIds = [];
        $integritasIds  = [];

        foreach ($allSub as $id => $s) {
            $name = $s->subindikator ?? '';

            if (stripos($name, 'masa kerja') !== false) {
                $masaIds[] = $id;
                continue;
            }

            // SKP: auto_sync flag AND name contains "Penilaian Kerja"
            if ($s->auto_sync && stripos($name, 'Penilaian Kerja') !== false) {
                $skpIds[] = $id;
                continue;
            }

            // Tingkat Pendidikan Formal: flag preferred, name-match fallback
            if (
                stripos($name, 'Tingkat Pendidikan Formal') !== false ||
                stripos($name, 'kualifikasi pendidikan') !== false
            ) {
                $kualifikasiIds[] = $id;
                continue;
            }

            // Integritas / Moralitas: auto_sync flag AND name matches
            if (
                $s->auto_sync && (
                    stripos($name, 'integritas') !== false ||
                    stripos($name, 'moralitas') !== false
                )
            ) {
                $integritasIds[] = $id;
            }
        }

        // Convert to hash sets for O(1) lookup
        $masaSet        = array_flip($masaIds);
        $skpSet         = array_flip($skpIds);
        $kualifikasiSet = array_flip($kualifikasiIds);
        $integritasSet  = array_flip($integritasIds);

        $query = Pegawai::with('penilaian');
        if ($filterNips !== null) {
            $query->whereIn('nip', $filterNips);
        }
        $pegawais = $query->get();

        $updated = 0;
        $errors  = [];

        foreach ($pegawais as $pegawai) {
            try {
                $jid    = $pegawai->jenis_jabatan_id ?? null;
                $nipStr = (string) ($pegawai->nip ?? '');

                $rec    = $pegawai->penilaian;
                $oldPen = ($rec && is_array($rec->penilaian)) ? $rec->penilaian : [];
                $newPen = [];

                // Closure: resolve stored nilai for a subId from $oldPen
                $oldNilai = static function ($id) use ($oldPen): float {
                    if (!array_key_exists($id, $oldPen)) return 0.0;
                    $e = $oldPen[$id];
                    return is_array($e)
                        ? (float) ($e['nilai'] ?? $e['hasil'] ?? 0)
                        : (is_numeric($e) ? (float) $e : 0.0);
                };

                // Fetch SKP kuadranKinerja string once per pegawai
                $skpKuadran = null;
                if (count($skpIds) > 0 && $nipStr !== '') {
                    $skpKuadran = $this->fetchKuadranKinerjaSKP($nipStr);
                }

                // Fetch hukuman disiplin records once per pegawai
                $hukumanRecords = null;
                if (count($integritasIds) > 0 && $nipStr !== '') {
                    $hukumanRecords = $this->fetchHukumanDisiplin($nipStr);
                }

                foreach ($allSub as $subId => $sub) {
                    $bobot              = (float) ($sub->bobot ?? 0);
                    $usesStandarMsk     = $sub->indikator->indikator === 'Penilaian Kompetensi Manajerial dan Sosial Kultural';
                    $usesStandarPotensi = $sub->indikator->indikator === 'Penilaian Potensi Talenta';
                    $nilai              = 0.0;

                    if (isset($masaSet[$subId])) {
                        // Masa Kerja: not yet auto-synced; preserve existing value
                        $nilai = $oldNilai($subId);
                    } elseif (isset($skpSet[$subId])) {
                        $nilai = $this->getNilaiSKPFromKuadran($skpKuadran, $instrBySub[$subId] ?? collect())
                            ?? $oldNilai($subId);
                    } elseif (isset($kualifikasiSet[$subId])) {
                        $nilai = $this->getNilaiTingkatPendidikanFormal($pegawai->json, $instrBySub[$subId] ?? collect())
                            ?? $oldNilai($subId);
                    } elseif (isset($integritasSet[$subId])) {
                        $nilai = $this->getNilaiIntegritasMoralitas($hukumanRecords, $instrBySub[$subId] ?? collect())
                            ?? $oldNilai($subId);
                    } else {
                        $nilai = $oldNilai($subId);
                    }

                    $standar = ($usesStandarMsk && $jid && isset($standarMap[$jid][$subId]))
                        ? (float) $standarMap[$jid][$subId]
                        : null;

                    $hasil = $this->computeHasil($nilai, $bobot, $usesStandarMsk, $usesStandarPotensi, $standar);

                    $newPen[$subId] = ['nilai' => round($nilai, 2), 'hasil' => round($hasil, 2)];
                }

                // Upsert
                if ($rec) {
                    if ($newPen !== $oldPen) {
                        $rec->penilaian = $newPen;
                        $rec->save();
                        $updated++;
                    }
                } else {
                    $nr = new Penilaian();
                    $nr->pegawai_id = $pegawai->id;
                    $nr->penilaian  = $newPen;
                    $nr->save();
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors[] = ['pegawai_nip' => $pegawai->nip ?? null, 'error' => $e->getMessage()];
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }
}
