<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\Penilaian;
use App\Models\SubIndikator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UmpanBalik360Service
{
    private string $filePath = 'settings/bobot_360.json';

    /**
     * Standard target number of evaluators per role.
     */
    private array $defaultTargets = [
        'atasan_langsung' => 1,
        'penilaian_diri' => 1,
        'rekan_kerja' => 3,
        'bawahan' => 3,
        'penerima_manfaat' => 3,
    ];

    /**
     * Default weight settings matching SettingBobot360Controller.
     */
    private array $defaultBobotSettings = [
        'jpt_madya' => [
            'atasan_langsung' => 40,
            'penerima_manfaat' => 0,
            'rekan_kerja' => 15,
            'bawahan' => 40,
            'penilaian_diri' => 5,
        ],
        'jpt_pratama' => [
            'atasan_langsung' => 40,
            'penerima_manfaat' => 0,
            'rekan_kerja' => 30,
            'bawahan' => 25,
            'penilaian_diri' => 5,
        ],
        'administrator' => [
            'atasan_langsung' => 40,
            'penerima_manfaat' => 0,
            'rekan_kerja' => 30,
            'bawahan' => 25,
            'penilaian_diri' => 5,
        ],
        'pengawas' => [
            'atasan_langsung' => 40,
            'penerima_manfaat' => 0,
            'rekan_kerja' => 30,
            'bawahan' => 25,
            'penilaian_diri' => 5,
        ],
        'pelaksana' => [
            'atasan_langsung' => 50,
            'penerima_manfaat' => 0,
            'rekan_kerja' => 45,
            'bawahan' => 0,
            'penilaian_diri' => 5,
        ],
        'jf' => [
            'atasan_langsung' => 30,
            'penerima_manfaat' => 30,
            'rekan_kerja' => 35,
            'bawahan' => 0,
            'penilaian_diri' => 5,
        ],
        'kepala_kantor' => [
            'atasan_langsung' => 50,
            'penerima_manfaat' => 0,
            'rekan_kerja' => 0,
            'bawahan' => 45,
            'penilaian_diri' => 5,
        ],
    ];

    /**
     * Fetch 360 Feedback data from NUSA API and sync to pegawai.riwayat_umpan_balik.
     */
    public function syncFromNusaApi(): array
    {
        $baseUrl = rtrim(env('NUSA_API_URL', 'https://nusa-be.dpd.go.id/api'), '/');
        $token = env('NUSA_API_TOKEN', '');

        try {
            $headers = $this->buildHeaders($token);
            $headers['Accept'] = 'application/json';

            $response = Http::withHeaders($headers)
                ->withOptions([
                    'verify' => false,
                ])
                ->timeout(60)
                ->get("{$baseUrl}/penilaian-pegawai", [
                    'with_pagination' => 'false',
                    'only_latest_periode' => 'true',
                    'with_template_penilaian' => 'true',
                ]);

            if (! $response->successful()) {
                Log::error('UmpanBalik360: NUSA API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Gagal mengambil data dari NUSA API: HTTP '.$response->status(),
                ];
            }

            $json = $response->json();
            $data = $json['data'] ?? [];

            if (! is_array($data)) {
                return [
                    'success' => false,
                    'message' => 'Format data dari NUSA API tidak valid.',
                ];
            }

            return $this->processAndSaveData($data);
        } catch (\Exception $e) {
            Log::error('UmpanBalik360 error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'message' => 'Gagal melakukan rekap Umpan Balik 360: '.$e->getMessage(),
            ];
        }
    }

    private function buildHeaders($token): array
    {
        $headers = [];

        if (! empty($token)) {
            $encryptedToken = TokenEncryptionService::encryptTokenForHeader(
                $token,
                ['salt' => $token]
            );
            $headers['X-Api-Token'] = $encryptedToken;
            $headers['origin'] = 'https://nusa-be.dpd.go.id';
        }

        return $headers;
    }

    /**
     * Process raw NUSA evaluation records and save recap to database.
     */
    public function processAndSaveData(array $records): array
    {
        // Group records by NIP pegawai -> periode
        $grouped = [];
        foreach ($records as $item) {
            $nip = trim((string) ($item['nip_pegawai'] ?? ''));
            $periode = trim((string) ($item['periode'] ?? ''));

            if ($nip === '' || $periode === '') {
                continue;
            }

            if (! isset($grouped[$nip])) {
                $grouped[$nip] = [];
            }
            if (! isset($grouped[$nip][$periode])) {
                $grouped[$nip][$periode] = [];
            }

            $grouped[$nip][$periode][] = $item;
        }

        $allBobotSettings = $this->getBobotSettings();
        $updatedCount = 0;
        $notFoundNips = [];

        $sub360 = SubIndikator::where('subindikator', 'like', '%Umpan Balik 360%')
            ->orWhere('subindikator', 'like', '%360%')
            ->first();

        foreach ($grouped as $nip => $periodeMap) {
            $pegawai = Pegawai::with('jenisJabatan')->where('nip', $nip)->first();

            if (! $pegawai) {
                $notFoundNips[] = $nip;

                continue;
            }

            $category = $this->determineJenisJabatanCategory($pegawai);
            $bobotMap = $this->resolveBobotForCategory($category, $allBobotSettings);

            $riwayat = is_array($pegawai->riwayat_umpan_balik) ? $pegawai->riwayat_umpan_balik : [];

            foreach ($periodeMap as $periode => $evaluations) {
                $rekapPeriod = $this->rekapForPeriod($periode, $category, $bobotMap, $evaluations);

                // Check if periode already exists in riwayat_umpan_balik
                $foundIndex = null;
                foreach ($riwayat as $idx => $entry) {
                    if (isset($entry['periode']) && $entry['periode'] === $periode) {
                        $foundIndex = $idx;
                        break;
                    }
                }

                if ($foundIndex !== null) {
                    $riwayat[$foundIndex] = $rekapPeriod;
                } else {
                    $riwayat[] = $rekapPeriod;
                }
            }

            $pegawai->riwayat_umpan_balik = $riwayat;
            $pegawai->save();

            // Update penilaians table for SubIndikator "Umpan Balik 360 Derajat"
            if ($sub360) {
                $latestRekap = null;
                if (! empty($riwayat)) {
                    $sortedRiwayat = $riwayat;
                    usort($sortedRiwayat, function ($a, $b) {
                        return strcmp($b['periode'] ?? '', $a['periode'] ?? '');
                    });
                    $latestRekap = $sortedRiwayat[0];
                }

                if ($latestRekap && isset($latestRekap['nilai_akhir'])) {
                    $nilai360 = (float) $latestRekap['nilai_akhir'];
                    $bobot360 = (float) ($sub360->bobot ?? 0);
                    $hasil360 = round($nilai360 * ($bobot360 / 100.0), 2);

                    $penilaianRec = Penilaian::where('pegawai_id', $pegawai->id)->first();
                    if (! $penilaianRec) {
                        $penilaianRec = new Penilaian;
                        $penilaianRec->pegawai_id = $pegawai->id;
                        $penData = [];
                    } else {
                        $penData = is_array($penilaianRec->penilaian) ? $penilaianRec->penilaian : [];
                    }

                    $penData[$sub360->id] = [
                        'nilai' => round($nilai360, 2),
                        'hasil' => round($hasil360, 2),
                    ];

                    $penilaianRec->penilaian = $penData;
                    $penilaianRec->save();
                }
            }

            $updatedCount++;
        }

        return [
            'success' => true,
            'message' => "Rekap Umpan Balik 360 berhasil diproses untuk {$updatedCount} pegawai.",
            'updated_count' => $updatedCount,
            'not_found_nips' => $notFoundNips,
        ];
    }

    /**
     * Compute 360 feedback recap for a single period.
     */
    private function rekapForPeriod(string $periode, string $category, array $bobotMap, array $evaluations): array
    {
        // 1. Group evaluation entries by normalized role
        $roleEvaluations = [
            'atasan_langsung' => [],
            'penerima_manfaat' => [],
            'rekan_kerja' => [],
            'bawahan' => [],
            'penilaian_diri' => [],
        ];

        $roleTotalAssigned = [
            'atasan_langsung' => 0,
            'penerima_manfaat' => 0,
            'rekan_kerja' => 0,
            'bawahan' => 0,
            'penilaian_diri' => 0,
        ];

        foreach ($evaluations as $eval) {
            $roleStr = (string) ($eval['role'] ?? '');
            $normalizedRole = $this->normalizeRole($roleStr);

            if (array_key_exists($normalizedRole, $roleEvaluations)) {
                $roleTotalAssigned[$normalizedRole]++;

                $pen = $eval['penilaian'] ?? null;
                // Only count as received/filled if evaluator actually submitted non-empty answers
                if (is_array($pen) && $this->isPenilaianFilled($pen)) {
                    $roleEvaluations[$normalizedRole][] = $pen;
                }
            }
        }

        // 2. Role completeness breakdown & total missing count
        $rolesSummary = [];
        $totalMissing = 0;

        foreach ($bobotMap as $roleKey => $bobot) {
            // receivedCount = jumlah pegawai (penilai) yang sudah mengisi penilaiannya
            $receivedCount = count($roleEvaluations[$roleKey] ?? []);

            // targetCount = jumlah total penilai yang harus mengisi penilaian
            if ($bobot > 0) {
                $defaultTarget = $this->defaultTargets[$roleKey] ?? 1;
                $assignedCount = $roleTotalAssigned[$roleKey] ?? 0;
                $targetCount = max($defaultTarget, $assignedCount, $receivedCount);
            } else {
                $targetCount = 0;
            }

            $statusStr = 'Lengkap';
            if ($targetCount > 0) {
                if ($receivedCount < $targetCount) {
                    $missing = $targetCount - $receivedCount;
                    $totalMissing += $missing;
                    $statusStr = "Kurang {$missing}";
                }
            }

            $rolesSummary[$roleKey] = [
                'role' => $roleKey,
                'bobot' => $bobot,
                'jumlah_penilai' => $receivedCount,
                'target_penilai' => $targetCount,
                'keterangan' => $statusStr,
            ];
        }

        $overallStatus = ($totalMissing === 0) ? 'Lengkap' : "Kurang {$totalMissing}";

        // 3. Rekap question1 to question21 (rating questions) & question22+ (text responses)
        $penilaianRekap = [];
        $ratingQuestionScores = [];

        // Question 1 to 21
        for ($i = 1; $i <= 21; $i++) {
            $qKey = "question{$i}";
            $questionScore = $this->calculateQuestionScore($qKey, $roleEvaluations, $bobotMap);
            $penilaianRekap[$qKey] = round($questionScore, 2);
            $ratingQuestionScores[] = $questionScore;
        }

        // Question 22 onwards (collect array of answers)
        // Find all question keys >= question22 present in evaluations
        $textQuestionKeys = [];
        foreach ($evaluations as $eval) {
            $pen = $eval['penilaian'] ?? [];
            if (! is_array($pen)) {
                continue;
            }
            foreach (array_keys($pen) as $k) {
                if (preg_match('/^question(\d+)$/i', $k, $m)) {
                    $qNum = (int) $m[1];
                    if ($qNum >= 22) {
                        $textQuestionKeys[$k] = true;
                    }
                }
            }
        }

        ksort($textQuestionKeys, SORT_NATURAL);

        foreach (array_keys($textQuestionKeys) as $qKey) {
            $answers = [];
            foreach ($evaluations as $eval) {
                $pen = $eval['penilaian'] ?? [];
                if (isset($pen[$qKey])) {
                    $ans = trim((string) $pen[$qKey]);
                    if ($ans !== '') {
                        $answers[] = $ans;
                    }
                }
            }
            $penilaianRekap[$qKey] = $answers;
        }

        // Calculate overall period final score (average of question1..question21)
        $nilaiAkhir = ! empty($ratingQuestionScores)
            ? round(array_sum($ratingQuestionScores) / count($ratingQuestionScores), 2)
            : 0.0;

        return [
            'periode' => $periode,
            'jenis_jabatan' => $category,
            'keterangan' => $overallStatus,
            'nilai_akhir' => $nilaiAkhir,
            'roles' => $rolesSummary,
            'penilaian' => $penilaianRekap,
        ];
    }

    /**
     * Calculate weighted score for a single question (question1..question21).
     * Rule: score = sum over active roles of ((avg_item_value / 5.0) * bobot_role)
     */
    private function calculateQuestionScore(string $qKey, array $roleEvaluations, array $bobotMap): float
    {
        $totalScore = 0.0;

        foreach ($bobotMap as $roleKey => $bobot) {
            if ($bobot <= 0) {
                continue;
            }

            $responses = $roleEvaluations[$roleKey] ?? [];
            if (empty($responses)) {
                continue;
            }

            $itemValues = [];
            foreach ($responses as $pen) {
                if (isset($pen[$qKey])) {
                    $val = $pen[$qKey];
                    $numVal = $this->extractItemValue($val);
                    if ($numVal !== null) {
                        $itemValues[] = $numVal;
                    }
                }
            }

            if (! empty($itemValues)) {
                $avgVal = array_sum($itemValues) / count($itemValues);
                // (avg / 5) * bobot
                $roleContribution = ($avgVal / 5.0) * $bobot;
                $totalScore += $roleContribution;
            }
        }

        return $totalScore;
    }

    /**
     * Extract integer rating value (1-5) from string like "Item 3" or numeric input.
     */
    private function extractItemValue($val): ?float
    {
        if (is_numeric($val)) {
            $num = (float) $val;

            return ($num >= 1 && $num <= 5) ? $num : null;
        }

        if (is_string($val)) {
            if (preg_match('/Item\s*(\d+)/i', $val, $m)) {
                $num = (float) $m[1];

                return ($num >= 1 && $num <= 5) ? $num : null;
            }
            if (preg_match('/^(\d+)$/', trim($val), $m)) {
                $num = (float) $m[1];

                return ($num >= 1 && $num <= 5) ? $num : null;
            }
        }

        return null;
    }

    /**
     * Normalize role name from NUSA API to standard weight key.
     */
    private function normalizeRole(string $role): string
    {
        $r = strtolower(trim($role));
        if (str_contains($r, 'atasan')) {
            return 'atasan_langsung';
        }
        if (str_contains($r, 'penerima')) {
            return 'penerima_manfaat';
        }
        if (str_contains($r, 'rekan') || str_contains($r, 'peer')) {
            return 'rekan_kerja';
        }
        if (str_contains($r, 'bawahan')) {
            return 'bawahan';
        }
        if (str_contains($r, 'diri') || str_contains($r, 'self')) {
            return 'penilaian_diri';
        }

        return preg_replace('/[^a-z0-9]+/', '_', $r);
    }

    /**
     * Determine position category (jenis_jabatan) based on user prompt mapping rules.
     */
    public function determineJenisJabatanCategory(Pegawai $pegawai): string
    {
        $jabatanName = trim((string) ($pegawai->jabatan_name ?? ''));
        $jenisJabatanName = trim((string) ($pegawai->jenisJabatan?->name ?? $pegawai->jenis_jabatan ?? ''));

        $jabatanLower = strtolower($jabatanName);
        $jenisLower = strtolower($jenisJabatanName);

        // 1. sekjen => pegawai.jabatan_name = "Sekretaris Jenderal DPD RI"
        if ($jabatanLower === 'sekretaris jenderal dpd ri') {
            return 'sekjen';
        }

        // 2. deputi => pegawai.jabatan_name diawali dengan "Deputi"
        if (str_starts_with($jabatanLower, 'deputi')) {
            return 'deputi';
        }

        // 3. kepala_kantor => pegawai.jabatan_name diawali dengan "Kepala Kantor DPD RI"
        if (str_starts_with($jabatanLower, 'kepala kantor dpd ri')) {
            return 'kepala_kantor';
        }

        // 4. jpt_pratama => jenis_jabatan_id.name = "Jabatan Pimpinan Tinggi Pratama"
        if ($jenisLower === 'jabatan pimpinan tinggi pratama' || $jenisLower === 'jpt pratama') {
            return 'jpt_pratama';
        }

        // 5. administrator => jenis_jabatan_id.name = "Jabatan Administrator"
        if ($jenisLower === 'jabatan administrator') {
            return 'administrator';
        }

        // 6. pengawas => jenis_jabatan_id.name = "Jabatan Pengawas"
        if ($jenisLower === 'jabatan pengawas') {
            return 'pengawas';
        }

        // 7. pelaksana => jenis_jabatan_id.name = "Jabatan Pelaksana"
        if ($jenisLower === 'jabatan pelaksana') {
            return 'pelaksana';
        }

        // 8. jf => jenis_jabatan_id.name diawali dengan "Jabatan Fungsional"
        if (str_starts_with($jenisLower, 'jabatan fungsional') || str_starts_with($jenisLower, 'jf')) {
            return 'jf';
        }

        // Default fallback
        return 'pelaksana';
    }

    /**
     * Get active weight settings from storage or defaults.
     */
    private function getBobotSettings(): array
    {
        if (Storage::disk('local')->exists($this->filePath)) {
            $content = Storage::disk('local')->get($this->filePath);
            $data = json_decode($content, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return $this->defaultBobotSettings;
    }

    /**
     * Resolve bobot configuration array for a given category.
     */
    private function resolveBobotForCategory(string $category, array $settings): array
    {
        if (isset($settings[$category])) {
            return $settings[$category];
        }

        if (in_array($category, ['sekjen', 'deputi']) && isset($settings['jpt_madya'])) {
            return $settings['jpt_madya'];
        }

        return $settings['pelaksana'] ?? [
            'atasan_langsung' => 50,
            'penerima_manfaat' => 0,
            'rekan_kerja' => 45,
            'bawahan' => 0,
            'penilaian_diri' => 5,
        ];
    }

    /**
     * Check if a penilaian array contains actual filled answer values.
     */
    private function isPenilaianFilled(array $pen): bool
    {
        foreach ($pen as $key => $val) {
            if (str_starts_with($key, 'question')) {
                $trimmed = is_string($val) ? trim($val) : $val;
                if ($trimmed !== null && $trimmed !== '') {
                    return true;
                }
            }
        }

        return false;
    }
}
