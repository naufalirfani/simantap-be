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
            return $standar > 0 ? ((($nilai < $standar) ? $nilai : $standar) / $standar) * 100.0 * ($bobot / 100.0) : 0.0;
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
     * Raw records are cached to pegawai.riwayat_skp; on API failure the cached value is used.
     * Returns null when the API call fails, the cache is empty, or the field is absent.
     *
     * @param  string  $nip
     * @param  Pegawai $pegawai  used to read/write the DB cache
     */
    public function fetchKuadranKinerjaSKP(string $nip, Pegawai $pegawai): ?string
    {
        $baseUrl = rtrim(env('OKK_API_BASE_URL', 'https://okk.dpd.go.id/dpd-portal/openapi/talenta/rw'), '/');
        $token   = env('OKK_API_TOKEN', '');

        $resolveKuadran = static function (?array $records): ?string {
            if (empty($records)) return null;
            usort($records, fn ($a, $b) => (int) ($b['tahun'] ?? 0) - (int) ($a['tahun'] ?? 0));
            $latest = $records[0];
            return !empty($latest['kuadranKinerja']) ? (string) $latest['kuadranKinerja'] : null;
        };

        try {
            $response = Http::withHeaders([
                'app-token'    => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get("{$baseUrl}/rw-skp22/{$nip}");

            if (!$response->successful()) {
                Log::warning("SKP API non-success for NIP {$nip}", ['status' => $response->status()]);
                return $resolveKuadran(is_array($pegawai->riwayat_skp) ? $pegawai->riwayat_skp : null);
            }

            $body    = $response->json();
            $records = data_get($body, 'data.data') ?? [];

            if (!is_array($records)) {
                Log::warning("SKP API: unexpected payload for NIP {$nip}", ['body' => $body]);
                return $resolveKuadran(is_array($pegawai->riwayat_skp) ? $pegawai->riwayat_skp : null);
            }

            // Persist to DB cache
            $pegawai->riwayat_skp = $records;
            $pegawai->saveQuietly();

            $kuadran = $resolveKuadran($records);
            if ($kuadran === null) {
                Log::warning("SKP API: kuadranKinerja not found for NIP {$nip}", ['body' => $body]);
            }
            return $kuadran;
        } catch (\Exception $e) {
            Log::error("SKP API error for NIP {$nip}: " . $e->getMessage());
            return $resolveKuadran(is_array($pegawai->riwayat_skp) ? $pegawai->riwayat_skp : null);
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
            $records = data_get($body, 'data.data') ?? [];

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
    // Riwayat Jabatan – external API + DB cache
    // ─────────────────────────────────────────────────────────────

    /**
     * Fetch riwayat jabatan for a single NIP from the OKK API.
     * On success, the result is persisted to pegawai.riwayat_jabatan as cache.
     * On failure, the cached value from the DB is returned instead.
     *
     * Endpoint: GET {OKK_API_BASE_URL}/rw-jabatan/{nip}
     *
     * @param  string  $nip
     * @param  Pegawai $pegawai  used to read/write the DB cache
     * @return array|null  raw records array, or null if both API and cache are unavailable
     */
    public function fetchRiwayatJabatan(string $nip, Pegawai $pegawai): ?array
    {
        $baseUrl = rtrim(env('OKK_API_BASE_URL', 'https://okk.dpd.go.id/dpd-portal/openapi/talenta/rw'), '/');
        $token   = env('OKK_API_TOKEN', '');

        try {
            $response = Http::withHeaders([
                'app-token'    => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get("{$baseUrl}/rw-jabatan/{$nip}");

            if (!$response->successful()) {
                Log::warning("RiwayatJabatan API non-success for NIP {$nip}", ['status' => $response->status()]);
                return is_array($pegawai->riwayat_jabatan) ? $pegawai->riwayat_jabatan : null;
            }

            $body    = $response->json();
            $records = data_get($body, 'data.data') ?? [];

            if (!is_array($records)) {
                Log::warning("RiwayatJabatan API: unexpected payload for NIP {$nip}", ['body' => $body]);
                return is_array($pegawai->riwayat_jabatan) ? $pegawai->riwayat_jabatan : null;
            }

            // Persist to DB cache
            $pegawai->riwayat_jabatan = $records;
            $pegawai->saveQuietly();

            return $records;
        } catch (\Exception $e) {
            Log::error("RiwayatJabatan API error for NIP {$nip}: " . $e->getMessage());
            return is_array($pegawai->riwayat_jabatan) ? $pegawai->riwayat_jabatan : null;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Lama Jabatan – derived from riwayat jabatan
    // ─────────────────────────────────────────────────────────────

    /**
     * Derive the nilai for "Lama Jabatan" by calculating how long the employee
     * has been in the current jenjang jabatan.
     *
     * Logic:
     *  1. Find the latest record (by createdAt).
     *  2. Based on its jenisJabatan:
     *     "1" → group by eselon level (e.g. "III.a"), pick earliest tmtJabatan in group.
     *     "2" → group by jabatan fungsional jenjang keyword (Ahli Utama/Madya/Muda/Pertama,
     *            Penyelia, Mahir, Terampil, Pemula), pick earliest tmtJabatan in group.
     *     "4" → group all jenisJabatan="4" records, pick earliest tmtJabatan.
     *  3. Calculate duration from that tmtJabatan to today.
     *  4. Match against instrumen scoring rules.
     *
     * @param  array|null $riwayatJabatan
     * @param  \Illuminate\Support\Collection $instrumens
     * @return float|null
     */
    public function getNilaiLamaJabatan(?array $riwayatJabatan, $instrumens): ?float
    {
        if (empty($riwayatJabatan) || $instrumens->isEmpty()) {
            return null;
        }

        /** Parse "DD-MM-YYYY" → Carbon or null */
        $parseDate = static function (?string $d): ?\Carbon\Carbon {
            if (!$d) return null;
            try {
                return \Carbon\Carbon::createFromFormat('d-m-Y', trim($d))->startOfDay();
            } catch (\Exception $e) {
                return null;
            }
        };

        /** Parse "DD-MM-YYYY" createdAt → Carbon or null */
        $parseCreatedAt = static function (?string $d): ?\Carbon\Carbon {
            if (!$d) return null;
            try {
                return \Carbon\Carbon::createFromFormat('d-m-Y', trim($d))->startOfDay();
            } catch (\Exception $e) {
                return null;
            }
        };

        // Find the latest record by createdAt
        $sorted = $riwayatJabatan;
        usort($sorted, function ($a, $b) use ($parseCreatedAt) {
            $ca = $parseCreatedAt($a['createdAt'] ?? null);
            $cb = $parseCreatedAt($b['createdAt'] ?? null);
            if (!$ca && !$cb) return 0;
            if (!$ca) return 1;
            if (!$cb) return -1;
            return $cb->gt($ca) ? 1 : ($cb->lt($ca) ? -1 : 0);
        });

        $latest      = $sorted[0];
        $jenisJabatan = (string) ($latest['jenisJabatan'] ?? '');

        /**
         * Extract fungsional jenjang keyword from jabatanFungsionalNama.
         * Priority: specific multi-word phrases first, then single words.
         */
        $extractFungsionalJenjang = static function (string $nama): ?string {
            $lower = mb_strtolower($nama);
            $keywords = [
                'ahli utama', 'ahli madya', 'ahli muda', 'ahli pertama',
                'penyelia', 'mahir', 'terampil', 'pemula',
            ];
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    return $kw;
                }
            }
            return null;
        };

        // Determine the earliest tmtJabatan within the relevant group
        $earliestTmt = null;

        if ($jenisJabatan === '1') {
            // Structural: group by eselon
            $eselon = trim((string) ($latest['eselon'] ?? ''));
            if ($eselon === '') {
                // fallback: use only the latest record's tmtJabatan
                $earliestTmt = $parseDate($latest['tmtJabatan'] ?? null);
            } else {
                foreach ($riwayatJabatan as $record) {
                    if ((string) ($record['jenisJabatan'] ?? '') !== '1') continue;
                    if (strtolower(trim((string) ($record['eselon'] ?? ''))) !== strtolower($eselon)) continue;
                    $tmt = $parseDate($record['tmtJabatan'] ?? null);
                    if ($tmt && ($earliestTmt === null || $tmt->lt($earliestTmt))) {
                        $earliestTmt = $tmt;
                    }
                }
            }
        } elseif ($jenisJabatan === '2') {
            // Fungsional: group by jenjang keyword
            $jenjang = $extractFungsionalJenjang((string) ($latest['jabatanFungsionalNama'] ?? ''));
            if ($jenjang === null) {
                $earliestTmt = $parseDate($latest['tmtJabatan'] ?? null);
            } else {
                foreach ($riwayatJabatan as $record) {
                    if ((string) ($record['jenisJabatan'] ?? '') !== '2') continue;
                    $recordJenjang = $extractFungsionalJenjang((string) ($record['jabatanFungsionalNama'] ?? ''));
                    if ($recordJenjang !== $jenjang) continue;
                    $tmt = $parseDate($record['tmtJabatan'] ?? null);
                    if ($tmt && ($earliestTmt === null || $tmt->lt($earliestTmt))) {
                        $earliestTmt = $tmt;
                    }
                }
            }
        } elseif ($jenisJabatan === '4') {
            // Pimpinan Tinggi: group all jenisJabatan=4 records
            foreach ($riwayatJabatan as $record) {
                if ((string) ($record['jenisJabatan'] ?? '') !== '4') continue;
                $tmt = $parseDate($record['tmtJabatan'] ?? null);
                if ($tmt && ($earliestTmt === null || $tmt->lt($earliestTmt))) {
                    $earliestTmt = $tmt;
                }
            }
        } else {
            // Unknown jenis: use latest record's own tmtJabatan
            $earliestTmt = $parseDate($latest['tmtJabatan'] ?? null);
        }

        if ($earliestTmt === null) {
            Log::warning("LamaJabatan: could not determine tmtJabatan from riwayat");
            return null;
        }

        // Cast to int to get whole completed years (Carbon 3+ may return a float)
        $yearsInPosition = (int) $earliestTmt->diffInYears(now());

        // Match against instrumens by checking year thresholds mentioned in the text
        // e.g. "5 tahun keatas", "3 s.d 4 tahun", "0 s.d 2 tahun"
        // Strategy: find the highest threshold the employee meets.
        // First, build a list of (minYears, maxYears|null, skor) from instrumens.
        $tiers = [];
        foreach ($instrumens as $ins) {
            $text  = strtolower($ins->instrumen ?? '');
            $skor  = (float) $ins->skor;

            // "5 tahun ke atas" / "5 tahun keatas" pattern
            if (preg_match('/(\d+)\s*tahun\s*ke\s*atas/u', $text, $m)) {
                $tiers[] = ['min' => (int) $m[1], 'max' => PHP_INT_MAX, 'skor' => $skor];
                continue;
            }
            // "X s.d Y tahun" / "X sd Y tahun" / "X - Y tahun" pattern
            if (preg_match('/(\d+)\s*(?:s\.?\s*d\.?|sd|sampai|hingga|-)\s*(\d+)\s*tahun/u', $text, $m)) {
                $tiers[] = ['min' => (int) $m[1], 'max' => (int) $m[2], 'skor' => $skor];
                continue;
            }
            // "X tahun" generic (treat as >= X)
            if (preg_match('/(\d+)\s*tahun/u', $text, $m)) {
                $tiers[] = ['min' => (int) $m[1], 'max' => PHP_INT_MAX, 'skor' => $skor];
            }
        }

        // Sort tiers descending by min so the highest qualifying tier wins
        usort($tiers, fn ($a, $b) => $b['min'] - $a['min']);

        foreach ($tiers as $tier) {
            if ($yearsInPosition >= $tier['min'] && $yearsInPosition <= $tier['max']) {
                return $tier['skor'];
            }
        }

        Log::warning("LamaJabatan: no instrumen tier matched for yearsInPosition={$yearsInPosition}");
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Keragaman Riwayat Jabatan – derived from riwayat jabatan
    // ─────────────────────────────────────────────────────────────

    /**
     * Derive the nilai for "Keragaman Riwayat Jabatan" by checking
     * whether the employee has worked across instansi, across unit kerja,
     * or only within one unit kerja.
     *
     * Logic:
     *  1. Lintas instansi  → multiple distinct satuanKerjaNama values exist.
     *  2. Lintas unit kerja → multiple distinct unorNama values exist (single instansi).
     *  3. 1 unit kerja      → all records share the same satuanKerjaNama and unorNama.
     *
     * Priority: lintas instansi > lintas unit kerja > satu unit kerja.
     *
     * @param  array|null $riwayatJabatan
     * @param  \Illuminate\Support\Collection $instrumens
     * @return float|null
     */
    public function getNilaiKeragamanRiwayatJabatan(?array $riwayatJabatan, $instrumens): ?float
    {
        if (empty($riwayatJabatan) || $instrumens->isEmpty()) {
            return null;
        }

        $satuanKerjas = array_unique(array_filter(array_map(
            fn ($r) => strtolower(trim((string) ($r['satuanKerjaNama'] ?? ''))),
            $riwayatJabatan
        )));

        $unorNamas = array_unique(array_filter(array_map(
            fn ($r) => strtolower(trim((string) ($r['unorNama'] ?? ''))),
            $riwayatJabatan
        )));

        $lintasInstansi   = count($satuanKerjas) > 1;
        $lintasUnitKerja  = count($unorNamas) > 1;

        if ($lintasInstansi) {
            $category = 'instansi';
        } elseif ($lintasUnitKerja) {
            $category = 'unit kerja';
        } else {
            $category = '1 unit kerja';
        }

        foreach ($instrumens as $ins) {
            $text = strtolower($ins->instrumen ?? '');
            $core = trim(preg_replace('/^[a-z][.\)]\s*/u', '', $text));

            $matched = match ($category) {
                'instansi'    => str_contains($core, 'instansi'),
                'unit kerja'  => str_contains($core, 'unit kerja') && !str_contains($core, 'instansi') && !str_contains($core, '1 unit'),
                '1 unit kerja'=> str_contains($core, '1 unit') || (str_contains($core, 'unit kerja') && str_contains($core, 'hanya')),
                default       => false,
            };

            if ($matched) {
                return (float) $ins->skor;
            }
        }

        Log::warning("KeragamanRiwayatJabatan: no instrumen match for category='{$category}'");
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Pengembangan Kompetensi – external API (rw-kursus) + DB cache
    // ─────────────────────────────────────────────────────────────

    /**
     * Fetch riwayat kursus / pengembangan kompetensi for a single NIP.
     *
     * Endpoint:  GET {OKK_API_BASE_URL}/rw-kursus/{nip}
     * Env vars:  OKK_API_BASE_URL, OKK_API_TOKEN
     *
     * On success the raw records array is persisted to
     * pegawai.riwayat_pengembangan_kompetensi as a DB cache.
     * On failure the cached value is returned instead.
     *
     * @param  string  $nip
     * @param  Pegawai $pegawai  used to read/write the DB cache
     * @return array|null  raw records array, or null if both API and cache are unavailable
     */
    public function fetchRiwayatPengembanganKompetensi(string $nip, Pegawai $pegawai): ?array
    {
        $baseUrl = rtrim(env('OKK_API_BASE_URL', 'https://okk.dpd.go.id/dpd-portal/openapi/talenta/rw'), '/');
        $token   = env('OKK_API_TOKEN', '');

        try {
            $response = Http::withHeaders([
                'app-token'    => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get("{$baseUrl}/rw-kursus/{$nip}");

            if (!$response->successful()) {
                Log::warning("PengembanganKompetensi API non-success for NIP {$nip}", ['status' => $response->status()]);
                return is_array($pegawai->riwayat_pengembangan_kompetensi)
                    ? $pegawai->riwayat_pengembangan_kompetensi
                    : null;
            }

            $body    = $response->json();
            $records = data_get($body, 'data.data') ?? [];

            if (!is_array($records)) {
                Log::warning("PengembanganKompetensi API: unexpected payload for NIP {$nip}", ['body' => $body]);
                return is_array($pegawai->riwayat_pengembangan_kompetensi)
                    ? $pegawai->riwayat_pengembangan_kompetensi
                    : null;
            }

            // Persist to DB cache
            $pegawai->riwayat_pengembangan_kompetensi = $records;
            $pegawai->saveQuietly();

            return $records;
        } catch (\Exception $e) {
            Log::error("PengembanganKompetensi API error for NIP {$nip}: " . $e->getMessage());
            return is_array($pegawai->riwayat_pengembangan_kompetensi)
                ? $pegawai->riwayat_pengembangan_kompetensi
                : null;
        }
    }

    /**
     * Derive the nilai for "Pengembangan Kompetensi" by counting training records
     * completed within the last 3 years and matching the count against instrumen
     * scoring tiers.
     *
     * Instrumen tiers expected (example):
     *   a. ...8 kali atau lebih     → count >= 8
     *   b. ...6-8 kali              → 6 <= count <= 7
     *   c. ...4-6 kali              → 4 <= count <= 5
     *   d. ...1-3 kali              → 1 <= count <= 3
     *   e. ...0 kali                → count == 0
     *
     * A record is counted when its tanggalSelesaiKursus (falling back to
     * tanggalKursus) can be parsed and falls within the last 3 years.
     *
     * @param  array|null $riwayat   raw records from fetchRiwayatPengembanganKompetensi()
     * @param  \Illuminate\Support\Collection $instrumens
     * @return float|null
     */
    public function getNilaiPengembanganKompetensi(?array $riwayat, $instrumens): ?float
    {
        if ($instrumens->isEmpty()) {
            return null;
        }

        /** Parse "DD-MM-YYYY" → Carbon or null */
        $parseDate = static function (?string $d): ?\Carbon\Carbon {
            if (!$d) return null;
            try {
                return \Carbon\Carbon::createFromFormat('d-m-Y', trim($d))->startOfDay();
            } catch (\Exception $e) {
                return null;
            }
        };

        $threeYearsAgo = now()->subYears(3)->startOfDay();
        $count         = 0;

        if (is_array($riwayat)) {
            // Deduplicate by noSertipikat: if multiple records share the same
            // non-empty noSertipikat value, only the first occurrence is kept.
            $seen      = [];
            $dedupedRiwayat = [];
            foreach ($riwayat as $record) {
                $noSert = trim((string) ($record['noSertipikat'] ?? ''));
                if ($noSert !== '' && $noSert !== '-') {
                    if (isset($seen[$noSert])) {
                        continue; // duplicate certificate number – skip
                    }
                    $seen[$noSert] = true;
                }
                $dedupedRiwayat[] = $record;
            }

            foreach ($dedupedRiwayat as $record) {
                // Exclude DIKLAT FUNGSIONAL – counted separately under Diklat scoring
                $jenisKursus = strtoupper(trim((string) ($record['jenisKursusSertifikat'] ?? '')));
                if ($jenisKursus === 'DIKLAT FUNGSIONAL') {
                    continue;
                }

                // Prefer tanggalSelesaiKursus, fall back to tanggalKursus
                $dateStr = $record['tanggalSelesaiKursus'] ?? null;
                if (empty($dateStr)) {
                    $dateStr = $record['tanggalKursus'] ?? null;
                }

                $date = $parseDate($dateStr);

                // Count if date is within the last 3 years (inclusive of boundary)
                if ($date !== null && $date->greaterThanOrEqualTo($threeYearsAgo)) {
                    $count++;
                }
            }
        }

        // Parse instrumens into tiers: [min, max, skor]
        // Patterns handled:
        //   "N kali atau lebih"  → min=N, max=INF
        //   "N-M kali" or "N s.d M kali" → min=N, max=M
        //   "0 kali"            → min=0, max=0
        $tiers = [];
        foreach ($instrumens as $ins) {
            $text = strtolower($ins->instrumen ?? '');
            $skor = (float) $ins->skor;

            // "N atau lebih" pattern
            if (preg_match('/(\d+)\s*kali\s*atau\s*lebih/u', $text, $m)) {
                $tiers[] = ['min' => (int) $m[1], 'max' => PHP_INT_MAX, 'skor' => $skor];
                continue;
            }

            // "N-M kali" or "N s.d M kali" or "N sd M kali"
            if (preg_match('/(\d+)\s*[-–]\s*(\d+)\s*kali/u', $text, $m)) {
                $tiers[] = ['min' => (int) $m[1], 'max' => (int) $m[2], 'skor' => $skor];
                continue;
            }
            if (preg_match('/(\d+)\s*(?:s\.?\s*d\.?|sd|sampai)\s*(\d+)\s*kali/u', $text, $m)) {
                $tiers[] = ['min' => (int) $m[1], 'max' => (int) $m[2], 'skor' => $skor];
                continue;
            }

            // "0 kali" exact zero
            if (preg_match('/\b0\s*kali\b/u', $text)) {
                $tiers[] = ['min' => 0, 'max' => 0, 'skor' => $skor];
            }
        }

        // Sort descending by min so the highest-threshold tier is checked first.
        // This ensures "8 atau lebih" wins over "6-8" when count == 8.
        usort($tiers, fn ($a, $b) => $b['min'] - $a['min']);

        foreach ($tiers as $tier) {
            if ($count >= $tier['min'] && $count <= $tier['max']) {
                return $tier['skor'];
            }
        }

        Log::warning("PengembanganKompetensi: no instrumen tier matched for count={$count}");
        return null;
    }

    // ─────────────────────────────────────────────────────────────
    // Diklat Kepemimpinan/Keahlian/Penjenjangan – API rw-diklat + rw-sertifikasi
    // ─────────────────────────────────────────────────────────────

    /**
     * Fetch riwayat diklat struktural for a single NIP from the OKK API.
     * On success the raw (de-duplicated) records are persisted to
     * pegawai.riwayat_diklat as a DB cache.
     * On failure the cached value is returned instead.
     *
     * Endpoint:  GET {OKK_API_BASE_URL}/rw-diklat/{nip}
     * De-duplication: records sharing the same non-empty `nomor` are collapsed
     * to a single entry.
     *
     * @param  string  $nip
     * @param  Pegawai $pegawai  used to read/write the DB cache
     * @return array|null  de-duplicated records, or null if both API and cache are unavailable
     */
    public function fetchRiwayatDiklatStruktural(string $nip, Pegawai $pegawai): ?array
    {
        $baseUrl = rtrim(env('OKK_API_BASE_URL', 'https://okk.dpd.go.id/dpd-portal/openapi/talenta/rw'), '/');
        $token   = env('OKK_API_TOKEN', '');

        $deduplicate = static function (array $records): array {
            $seen    = [];
            $cleaned = [];
            foreach ($records as $record) {
                $nomor = trim((string) ($record['nomor'] ?? ''));
                if ($nomor !== '') {
                    if (isset($seen[$nomor])) {
                        continue; // duplicate nomor – skip
                    }
                    $seen[$nomor] = true;
                }
                $cleaned[] = $record;
            }
            return $cleaned;
        };

        try {
            $response = Http::withHeaders([
                'app-token'    => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get("{$baseUrl}/rw-diklat/{$nip}");

            if (!$response->successful()) {
                Log::warning("DiklatStruktural API non-success for NIP {$nip}", ['status' => $response->status()]);
                return is_array($pegawai->riwayat_diklat) ? $pegawai->riwayat_diklat : null;
            }

            $body    = $response->json();
            $records = data_get($body, 'data.data') ?? [];

            if (!is_array($records)) {
                Log::warning("DiklatStruktural API: unexpected payload for NIP {$nip}", ['body' => $body]);
                return is_array($pegawai->riwayat_diklat) ? $pegawai->riwayat_diklat : null;
            }

            $records = $deduplicate($records);

            // Persist to DB cache
            $pegawai->riwayat_diklat = $records;
            $pegawai->saveQuietly();

            return $records;
        } catch (\Exception $e) {
            Log::error("DiklatStruktural API error for NIP {$nip}: " . $e->getMessage());
            return is_array($pegawai->riwayat_diklat) ? $pegawai->riwayat_diklat : null;
        }
    }

    /**
     * Fetch riwayat sertifikasi for a single NIP from the OKK API.
     * On success the raw (de-duplicated) records are persisted to
     * pegawai.riwayat_sertifikasi as a DB cache.
     * On failure the cached value is returned instead.
     *
     * Endpoint:  GET {OKK_API_BASE_URL}/rw-sertifikasi/{nip}
     * De-duplication: records sharing the same non-empty `noSertifikat` are
     * collapsed to a single entry.
     *
     * @param  string  $nip
     * @param  Pegawai $pegawai  used to read/write the DB cache
     * @return array|null  de-duplicated records, or null if both API and cache are unavailable
     */
    public function fetchRiwayatSertifikasi(string $nip, Pegawai $pegawai): ?array
    {
        $baseUrl = rtrim(env('OKK_API_BASE_URL', 'https://okk.dpd.go.id/dpd-portal/openapi/talenta/rw'), '/');
        $token   = env('OKK_API_TOKEN', '');

        $deduplicate = static function (array $records): array {
            $seen    = [];
            $cleaned = [];
            foreach ($records as $record) {
                $noSert = trim((string) ($record['noSertifikat'] ?? ''));
                if ($noSert !== '') {
                    if (isset($seen[$noSert])) {
                        continue; // duplicate noSertifikat – skip
                    }
                    $seen[$noSert] = true;
                }
                $cleaned[] = $record;
            }
            return $cleaned;
        };

        try {
            $response = Http::withHeaders([
                'app-token'    => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get("{$baseUrl}/rw-sertifikasi/{$nip}");

            if (!$response->successful()) {
                Log::warning("Sertifikasi API non-success for NIP {$nip}", ['status' => $response->status()]);
                return is_array($pegawai->riwayat_sertifikasi) ? $pegawai->riwayat_sertifikasi : null;
            }

            $body    = $response->json();
            $records = data_get($body, 'data.data') ?? [];

            if (!is_array($records)) {
                Log::warning("Sertifikasi API: unexpected payload for NIP {$nip}", ['body' => $body]);
                return is_array($pegawai->riwayat_sertifikasi) ? $pegawai->riwayat_sertifikasi : null;
            }

            $records = $deduplicate($records);

            // Persist to DB cache
            $pegawai->riwayat_sertifikasi = $records;
            $pegawai->saveQuietly();

            return $records;
        } catch (\Exception $e) {
            Log::error("Sertifikasi API error for NIP {$nip}: " . $e->getMessage());
            return is_array($pegawai->riwayat_sertifikasi) ? $pegawai->riwayat_sertifikasi : null;
        }
    }

    /**
     * Derive the nilai for "Diklat Kepemimpinan/Keahlian/Penjenjangan" by
     * combining the count from riwayat diklat struktural (all records, no year
     * filter) with the count from riwayat sertifikasi (only records whose
     * tanggalSertifikat falls within the last 3 years), then matching the
     * combined total against instrumen scoring tiers.
     *
     * Counting rules:
     *  - API 1 (rw-diklat):     count all de-duplicated records regardless of year.
     *  - API 2 (rw-sertifikasi): count only records where tanggalSertifikat >= 3 years ago.
     *  - Total = count(diklat) + count(sertifikasi within 3 years).
     *
     * Instrumen tier examples:
     *   "a. Jumlah Sertifikasi dalam 3 tahun terakhir sebanyak 3 kali atau lebih"
     *   "b. Jumlah Sertifikasi dalam 3 tahun terakhir sebanyak 1-2 kali"
     *   "c. Jumlah Sertifikasi dalam 3 tahun terakhir sebanyak 0 kali"
     *
     * @param  array|null $riwayatDiklat       de-duplicated records from fetchRiwayatDiklatStruktural()
     * @param  array|null $riwayatSertifikasi  de-duplicated records from fetchRiwayatSertifikasi()
     * @param  \Illuminate\Support\Collection $instrumens
     * @return float|null
     */
    public function getNilaiDiklatKepemimpinan(
        ?array $riwayatDiklat,
        ?array $riwayatSertifikasi,
        $instrumens,
        ?array $riwayatKursus = null
    ): ?float {
        if ($instrumens->isEmpty()) {
            return null;
        }

        /** Parse "DD-MM-YYYY" → Carbon or null */
        $parseDate = static function (?string $d): ?\Carbon\Carbon {
            if (!$d) return null;
            try {
                return \Carbon\Carbon::createFromFormat('d-m-Y', trim($d))->startOfDay();
            } catch (\Exception $e) {
                return null;
            }
        };

        // Count all diklat struktural records (no year restriction)
        $diklatCount = is_array($riwayatDiklat) ? count($riwayatDiklat) : 0;

        // Count sertifikasi records within the last 3 years
        $threeYearsAgo    = now()->subYears(3)->startOfDay();
        $sertifikasiCount = 0;

        if (is_array($riwayatSertifikasi)) {
            foreach ($riwayatSertifikasi as $record) {
                $dateStr = $record['tanggalSertifikat'] ?? null;
                $date    = $parseDate($dateStr);
                if ($date !== null && $date->greaterThanOrEqualTo($threeYearsAgo)) {
                    $sertifikasiCount++;
                }
            }
        }

        // Count riwayat kursus (Pengembangan Kompetensi) records where
        // jenisKursusSertifikat = "DIKLAT FUNGSIONAL" (all records, no year filter)
        $diklatFungsionalCount = 0;
        if (is_array($riwayatKursus)) {
            $seenKursus = [];
            foreach ($riwayatKursus as $record) {
                $jenis = strtoupper(trim((string) ($record['jenisKursusSertifikat'] ?? '')));
                if ($jenis !== 'DIKLAT FUNGSIONAL') {
                    continue;
                }
                // Deduplicate by noSertipikat (same as Pengembangan Kompetensi logic)
                $noSert = trim((string) ($record['noSertipikat'] ?? ''));
                if ($noSert !== '' && $noSert !== '-') {
                    if (isset($seenKursus[$noSert])) {
                        continue;
                    }
                    $seenKursus[$noSert] = true;
                }
                $diklatFungsionalCount++;
            }
        }

        $totalCount = $diklatCount + $sertifikasiCount + $diklatFungsionalCount;

        // Parse instrumens into tiers: [min, max, skor]
        // Patterns handled:
        //   "N kali atau lebih"  → min=N, max=INF
        //   "N-M kali" or "N s.d M kali" → min=N, max=M
        //   "0 kali"            → min=0, max=0
        $tiers = [];
        foreach ($instrumens as $ins) {
            $text = strtolower($ins->instrumen ?? '');
            $skor = (float) $ins->skor;

            // "N kali atau lebih" or "N atau lebih kali"
            if (preg_match('/(\d+)\s*kali\s*atau\s*lebih/u', $text, $m)) {
                $tiers[] = ['min' => (int) $m[1], 'max' => PHP_INT_MAX, 'skor' => $skor];
                continue;
            }
            if (preg_match('/(\d+)\s*atau\s*lebih\s*kali/u', $text, $m)) {
                $tiers[] = ['min' => (int) $m[1], 'max' => PHP_INT_MAX, 'skor' => $skor];
                continue;
            }

            // "N-M kali" or "N s.d M kali"
            if (preg_match('/(\d+)\s*[-–]\s*(\d+)\s*kali/u', $text, $m)) {
                $tiers[] = ['min' => (int) $m[1], 'max' => (int) $m[2], 'skor' => $skor];
                continue;
            }
            if (preg_match('/(\d+)\s*(?:s\.?\s*d\.?|sd|sampai)\s*(\d+)\s*kali/u', $text, $m)) {
                $tiers[] = ['min' => (int) $m[1], 'max' => (int) $m[2], 'skor' => $skor];
                continue;
            }

            // "0 kali" exact zero
            if (preg_match('/\b0\s*kali\b/u', $text)) {
                $tiers[] = ['min' => 0, 'max' => 0, 'skor' => $skor];
            }
        }

        // Sort descending by min so highest-threshold tier is checked first
        usort($tiers, fn ($a, $b) => $b['min'] - $a['min']);

        foreach ($tiers as $tier) {
            if ($totalCount >= $tier['min'] && $totalCount <= $tier['max']) {
                return $tier['skor'];
            }
        }

        Log::warning("DiklatKepemimpinan: no instrumen tier matched for totalCount={$totalCount}");
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
        $lamaJabatanIds = [];
        $keragamanIds   = [];
        $pengembanganKompetensiIds = [];
        $diklatIds      = [];

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
                continue;
            }

            // Lama Jabatan: auto_sync flag AND name matches
            if ($s->auto_sync && stripos($name, 'Lama Jabatan') !== false) {
                $lamaJabatanIds[] = $id;
                continue;
            }

            // Keragaman Riwayat Jabatan: auto_sync flag AND name matches
            if ($s->auto_sync && stripos($name, 'Keragaman Riwayat Jabatan') !== false) {
                $keragamanIds[] = $id;
                continue;
            }

            // Pengembangan Kompetensi: auto_sync flag AND name matches
            if ($s->auto_sync && stripos($name, 'Pengembangan Kompetensi') !== false) {
                $pengembanganKompetensiIds[] = $id;
                continue;
            }

            // Diklat Kepemimpinan/Keahlian/Penjenjangan: auto_sync flag AND name matches
            if (
                $s->auto_sync && (
                    stripos($name, 'Diklat Kepemimpinan') !== false ||
                    stripos($name, 'Diklat Keahlian')     !== false ||
                    stripos($name, 'Diklat Penjenjangan') !== false
                )
            ) {
                $diklatIds[] = $id;
            }
        }

        // Convert to hash sets for O(1) lookup
        $masaSet        = array_flip($masaIds);
        $skpSet         = array_flip($skpIds);
        $kualifikasiSet = array_flip($kualifikasiIds);
        $integritasSet  = array_flip($integritasIds);
        $lamaJabatanSet = array_flip($lamaJabatanIds);
        $keragamanSet   = array_flip($keragamanIds);
        $pengembanganKompetensiSet = array_flip($pengembanganKompetensiIds);
        $diklatSet                 = array_flip($diklatIds);

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
                    $skpKuadran = $this->fetchKuadranKinerjaSKP($nipStr, $pegawai);
                }

                // Fetch hukuman disiplin records once per pegawai
                $hukumanRecords = null;
                if (count($integritasIds) > 0 && $nipStr !== '') {
                    $hukumanRecords = $this->fetchHukumanDisiplin($nipStr);
                }

                // Fetch riwayat jabatan records once per pegawai (Lama Jabatan & Keragaman)
                $riwayatJabatan = null;
                if ((count($lamaJabatanIds) > 0 || count($keragamanIds) > 0) && $nipStr !== '') {
                    $riwayatJabatan = $this->fetchRiwayatJabatan($nipStr, $pegawai);
                }

                // Fetch riwayat kursus once per pegawai (Pengembangan Kompetensi + Diklat Fungsional)
                $riwayatPengembanganKompetensi = null;
                if ((count($pengembanganKompetensiIds) > 0 || count($diklatIds) > 0) && $nipStr !== '') {
                    $riwayatPengembanganKompetensi = $this->fetchRiwayatPengembanganKompetensi($nipStr, $pegawai);
                }

                // Fetch riwayat diklat struktural and sertifikasi once per pegawai
                $riwayatDiklat      = null;
                $riwayatSertifikasi = null;
                if (count($diklatIds) > 0 && $nipStr !== '') {
                    $riwayatDiklat      = $this->fetchRiwayatDiklatStruktural($nipStr, $pegawai);
                    $riwayatSertifikasi = $this->fetchRiwayatSertifikasi($nipStr, $pegawai);
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
                    } elseif (isset($lamaJabatanSet[$subId])) {
                        $nilai = $this->getNilaiLamaJabatan($riwayatJabatan, $instrBySub[$subId] ?? collect())
                            ?? $oldNilai($subId);
                    } elseif (isset($keragamanSet[$subId])) {
                        $nilai = $this->getNilaiKeragamanRiwayatJabatan($riwayatJabatan, $instrBySub[$subId] ?? collect())
                            ?? $oldNilai($subId);
                    } elseif (isset($pengembanganKompetensiSet[$subId])) {
                        $nilai = $this->getNilaiPengembanganKompetensi($riwayatPengembanganKompetensi, $instrBySub[$subId] ?? collect())
                            ?? $oldNilai($subId);
                    } elseif (isset($diklatSet[$subId])) {
                        $nilai = $this->getNilaiDiklatKepemimpinan($riwayatDiklat, $riwayatSertifikasi, $instrBySub[$subId] ?? collect(), $riwayatPengembanganKompetensi)
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

                // Stamp last_sync_penilaian on every successful sync (regardless of whether data changed)
                $pegawai->last_sync_penilaian = now();
                $pegawai->saveQuietly();
            } catch (\Exception $e) {
                $errors[] = ['pegawai_nip' => $pegawai->nip ?? null, 'error' => $e->getMessage()];
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }
}
