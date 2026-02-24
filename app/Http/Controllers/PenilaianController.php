<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Penilaian;
use App\Models\SubIndikator;
use App\Models\StandarKompetensiMsk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;
use App\Models\Instrumen;

class PenilaianController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Penilaian::query();
            if ($request->filled('pegawai_id')) {
                $query->where('pegawai_id', $request->get('pegawai_id'));
            }

            $perPage = (int) $request->get('per_page', 15);
            $perPage = max(1, min($perPage, 100));

            return response()->json($query->orderBy('id', 'desc')->paginate($perPage));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate nilai for Masa kerja based on years and instrumen rules.
     * Parses instrumen text to extract year ranges and map to scores dynamically.
     */
    private function generateNilaiMasaKerja($pegawaiJson, $instrumens)
    {
        // extract tmtCpns from pegawai json
        $tmt = data_get($pegawaiJson, 'tmtCpns');
        if (empty($tmt)) {
            $tmt = data_get($pegawaiJson, 'tmt_cpns');
        }

        $years = null;
        if (!empty($tmt)) {
            try {
                $dt = Carbon::parse($tmt);
                $years = $dt->diffInYears(Carbon::now());
            } catch (\Exception $e) {
                $years = null;
            }
        }

        if ($years === null) {
            return 0.0;
        }

        // Parse each instrumen to extract year range and skor
        $rules = [];
        foreach ($instrumens as $ins) {
            $text = strtolower($ins->instrumen ?? '');
            $skor = (float) $ins->skor;

            // Pattern: "X tahun keatas" or "X tahun ke atas"
            if (preg_match('/(\d+)\s*tahun\s*ke\s*atas/', $text, $m)) {
                $min = (int) $m[1];
                $rules[] = ['type' => 'gte', 'min' => $min, 'skor' => $skor];
                continue;
            }

            // Pattern: ">X s.d Y tahun" (exclusive lower, inclusive upper)
            if (preg_match('/>(\d+)\s*s\.?d\.?\s*(\d+)\s*tahun/', $text, $m)) {
                $min = (int) $m[1];
                $max = (int) $m[2];
                $rules[] = ['type' => 'gt_lte', 'min' => $min, 'max' => $max, 'skor' => $skor];
                continue;
            }

            // Pattern: "X s.d Y tahun" (inclusive both, typically for lower bound like 0-5)
            if (preg_match('/(\d+)\s*s\.?d\.?\s*(\d+)\s*tahun/', $text, $m)) {
                $min = (int) $m[1];
                $max = (int) $m[2];
                $rules[] = ['type' => 'gte_lte', 'min' => $min, 'max' => $max, 'skor' => $skor];
                continue;
            }
        }

        // Match years against rules
        foreach ($rules as $rule) {
            if ($rule['type'] === 'gte' && $years >= $rule['min']) {
                return $rule['skor'];
            } elseif ($rule['type'] === 'gt_lte' && $years > $rule['min'] && $years <= $rule['max']) {
                return $rule['skor'];
            } elseif ($rule['type'] === 'gte_lte' && $years >= $rule['min'] && $years <= $rule['max']) {
                return $rule['skor'];
            }
        }

        // Fallback: return 0 if no rule matches
        return 0.0;
    }

    /**
     * Generate nilai for Tingkat Pendidikan Formal (education) based on pegawai json and instrumen rules.
     * Accepts values like 's2', 's-2', 's.2' (case-insensitive) in pegawai.json.tkPendidikanTerakhir
     */
    private function generateNilaiTingkatPendidikanFormal($pegawaiJson, $instrumens)
    {
        // extract education string
        $edu = data_get($pegawaiJson, 'tkPendidikanTerakhir');
        if (empty($edu)) {
            $edu = data_get($pegawaiJson, 'tk_pendidikan_terakhir');
        }

        $normEdu = '';
        if (!empty($edu)) {
            $normEdu = strtolower(trim((string) $edu));
            // normalize variants: s.2, s-2 -> s2
            $normEdu = preg_replace('/[\.\-\s]+/', '', $normEdu);
        }

        // map normalized education to canonical level tokens
        $level = null;
        if ($normEdu !== '') {
            if (preg_match('/s3|strata3|strataiii/', $normEdu)) {
                $level = 'S3';
            } elseif (preg_match('/s2|strata2|strataii/', $normEdu)) {
                $level = 'S2';
            } elseif (preg_match('/s1|d4|d.iv|d\.4|strata1|stratai/', $normEdu)) {
                $level = 'S1/D4';
            } elseif (preg_match('/d3|d\.3|diii|d3/i', $normEdu)) {
                $level = 'D3';
            } elseif (preg_match('/slta|sma|smk|ma|sma\/ma|sekolahlanjutantingkatatas/', $normEdu)) {
                $level = 'SLTA';
            } else {
                // fallback: try to detect digits
                if (strpos($normEdu, '3') !== false) $level = 'S3';
                if (!$level && strpos($normEdu, '2') !== false) $level = 'S2';
                if (!$level && (strpos($normEdu, '1') !== false || strpos($normEdu, 'd4') !== false)) $level = 'S1/D4';
            }
        }

        // build mapping from instrumen entries to scores by detecting keywords
        $mapping = [];
        foreach ($instrumens as $ins) {
            $text = strtolower($ins->instrumen ?? '');
            $skor = (float) $ins->skor;

            $t = preg_replace('/[\.\-\s]+/', '', $text);
            if (stripos($t, 's3') !== false || stripos($text, 'strata 3') !== false) {
                $mapping['S3'] = $skor;
                continue;
            }
            if (stripos($t, 's2') !== false || stripos($text, 'strata 2') !== false) {
                $mapping['S2'] = $skor;
                continue;
            }
            if (stripos($t, 's1') !== false || stripos($t, 'd4') !== false || stripos($text, 'strata 1') !== false) {
                $mapping['S1/D4'] = $skor;
                continue;
            }
            if (stripos($t, 'd3') !== false || stripos($text, 'diploma iii') !== false) {
                $mapping['D3'] = $skor;
                continue;
            }
            if (stripos($t, 'slta') !== false || stripos($text, 'sekolah lanjutan') !== false || stripos($text, 'sma') !== false) {
                $mapping['SLTA'] = $skor;
                continue;
            }
        }

        // if level detected and mapping has it, return mapped skor
        if ($level && array_key_exists($level, $mapping)) {
            return $mapping[$level];
        }

        // fallback: try direct match of instrumen skor by searching normalized edu in instrumen text
        foreach ($instrumens as $ins) {
            $text = strtolower($ins->instrumen ?? '');
            if ($normEdu !== '' && stripos(preg_replace('/[\.\-\s]+/', '', $text), $normEdu) !== false) {
                return (float) $ins->skor;
            }
        }

        return 0.0;
    }

    /**
     * Recalculate and sync all penilaian records.
     * Can be triggered via POST /api/penilaians/sync
     */
    public function sync(Request $request)
    {
        try {
            set_time_limit(600);

            // Load subindikator metadata
            $allSub = SubIndikator::with('indikator')->get()->keyBy('id');

            // Load instrumen per subindikator
            $instrBySub = Instrumen::all()->groupBy('subindikator_id');

            // Load standar kompetensi map [jenis_jabatan_id][subindikator_id] => standar
            $allStandar = StandarKompetensiMsk::all();
            $standarMap = [];
            foreach ($allStandar as $st) {
                $jid = $st->jenis_jabatan_id;
                $sid = $st->subindikator_id;
                $standarMap[$jid][$sid] = (float) $st->standar;
            }

            // identify 'masa kerja' subindikator ids (case-insensitive match)
            $masaIds = [];
            foreach ($allSub as $id => $s) {
                if (stripos($s->subindikator ?? '', 'masa kerja') !== false) {
                    $masaIds[] = $id;
                }
            }

            // identify 'kualifikasi' subindikator ids (case-insensitive match)
            $kualifikasiIds = [];
            foreach ($allSub as $id => $s) {
                if (stripos($s->subindikator ?? '', 'Tingkat Pendidikan Formal') !== false || stripos($s->subindikator ?? '', 'kualifikasi pendidikan') !== false) {
                    $kualifikasiIds[] = $id;
                }
            }

            $updated = 0;
            $errors = [];

            $pegawais = Pegawai::with('penilaian')->get();
            foreach ($pegawais as $pegawai) {
                try {
                    if (!$pegawai) continue;
                    $jid = $pegawai->jenis_jabatan_id ?? null;

                    $rec = $pegawai->penilaian; // may be null
                    $oldPen = ($rec && is_array($rec->penilaian)) ? $rec->penilaian : [];
                    $newPen = [];

                    foreach ($allSub as $subId => $sub) {
                        $bobot = (float) ($sub->bobot ?? 0);
                        $usesStandarMsk = $sub->indikator->indikator === 'Penilaian Kompetensi Manajerial dan Sosial Kultural';
                        $usesStandarPotensi = $sub->indikator->indikator === 'Penilaian Potensi Talenta';

                        // default nilai
                        $nilai = 0.0;

                        // Special handling for Masa kerja and Tingkat Pendidikan Formal
                        if (in_array($subId, $masaIds, true)) {
                            $instrs = $instrBySub[$subId] ?? collect();
                            // $nilai = $this->generateNilaiMasaKerja($pegawai->json, $instrs);
                        } elseif (in_array($subId, $kualifikasiIds, true)) {
                            $instrs = $instrBySub[$subId] ?? collect();
                            $nilai = $this->generateNilaiTingkatPendidikanFormal($pegawai->json, $instrs);
                        } else {
                            // use existing value if available, otherwise default 0
                            if (array_key_exists($subId, $oldPen)) {
                                $entry = $oldPen[$subId];
                                if (is_array($entry)) {
                                    if (array_key_exists('nilai', $entry)) {
                                        $nilai = (float) $entry['nilai'];
                                    } elseif (array_key_exists('hasil', $entry)) {
                                        $nilai = (float) $entry['hasil'];
                                    } else {
                                        $nilai = 0.0;
                                    }
                                } elseif (is_numeric($entry)) {
                                    $nilai = (float) $entry;
                                } else {
                                    $nilai = 0.0;
                                }
                            } else {
                                $nilai = 0.0;
                            }
                        }

                        // compute hasil
                        $hasil = 0.0;
                        if ($usesStandarMsk) {
                            $standar = $jid && isset($standarMap[$jid][$subId]) ? (float) $standarMap[$jid][$subId] : 0.0;
                            if ($standar > 0) {
                                $hasil = ($nilai / $standar) * 100.0 * ($bobot / 100.0);
                            } else {
                                $hasil = 0.0;
                            }
                        } elseif ($usesStandarPotensi) {
                            $hasil = ($nilai / 5) * 100.0 * ($bobot / 100.0);
                        } else {
                            $hasil = $nilai * ($bobot / 100.0);
                        }

                        $newPen[$subId] = ['nilai' => round($nilai, 2), 'hasil' => round($hasil, 2)];
                    }

                    // upsert penilaian per pegawai
                    if ($rec) {
                        if ($newPen !== $oldPen) {
                            $rec->penilaian = $newPen;
                            $rec->save();
                            $updated++;
                        }
                    } else {
                        $nr = new Penilaian();
                        $nr->pegawai_id = $pegawai->id;
                        $nr->penilaian = $newPen;
                        $nr->save();
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $errors[] = ['pegawai_nip' => $pegawai->nip ?? null, 'error' => $e->getMessage()];
                }
            }

            return response()->json(['success' => true, 'updated' => $updated, 'errors' => $errors], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Sync failed', 'error' => $e->getMessage()], 500);
        }
    }

    private function normalizeName(?string $s): string
    {
        if ($s === null) {
            return '';
        }
        $s = trim(mb_strtolower($s));
        // transliterate accents to ASCII when possible
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($trans !== false) {
            $s = $trans;
        }
        // replace non-alphanumeric with single space
        $s = preg_replace('/[^a-z0-9]+/i', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pegawai_id' => 'required|exists:pegawai,id',
            'penilaian' => 'required|array',
            'penilaian.*' => 'required|array',
            'penilaian.*.nilai' => 'required|numeric',
            'penilaian.*.hasil' => 'required|numeric',
        ]);

        // Validate keys are UUIDs and correspond to existing SubIndikator ids
        $keys = array_keys($data['penilaian']);
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        foreach ($keys as $k) {
            if (!preg_match($uuidPattern, $k)) {
                return response()->json(['message' => "Invalid subindikator_id: {$k}"], 422);
            }
        }

        $existing = SubIndikator::whereIn('id', $keys)->pluck('id')->all();
        if (count($existing) !== count($keys)) {
            $missing = array_diff($keys, $existing);
            return response()->json(['message' => 'Some subindikator_id not found', 'missing' => array_values($missing)], 422);
        }

        // Normalize and store both 'nilai' and 'hasil' as numbers with 2 decimal places
        $penilaian = [];
        foreach ($data['penilaian'] as $k => $entry) {
            $penilaian[$k] = [
                'nilai' => round((float) ($entry['nilai'] ?? 0), 2),
                'hasil' => round((float) ($entry['hasil'] ?? 0), 2),
            ];
        }

        $record = Penilaian::create([
            'pegawai_id' => $data['pegawai_id'],
            'penilaian' => $penilaian,
        ]);

        return response()->json($record, 201);
    }

    public function show($id)
    {
        // Treat $id as pegawai NIP: find pegawai, then its latest penilaian
        $pegawai = Pegawai::where('nip', $id)->first();

        if (!$pegawai) {
            return response()->json(null, 200);
        }

        $record = Penilaian::where('pegawai_id', $pegawai->id)->orderBy('created_at', 'desc')->first();

        if (!$record) {
            return response()->json(null, 200);
        }

        return response()->json($record);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'pegawai_id' => 'sometimes|required|exists:pegawai,id',
            'penilaian' => 'sometimes|required|array',
            'penilaian.*' => 'required_with:penilaian|array',
            'penilaian.*.nilai' => 'required_with:penilaian|numeric',
            'penilaian.*.hasil' => 'required_with:penilaian|numeric',
        ]);

        $record = Penilaian::findOrFail($id);

        if (isset($data['penilaian'])) {
            $keys = array_keys($data['penilaian']);
            $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
            foreach ($keys as $k) {
                if (!preg_match($uuidPattern, $k)) {
                    return response()->json(['message' => "Invalid subindikator_id: {$k}"], 422);
                }
            }

            $existing = SubIndikator::whereIn('id', $keys)->pluck('id')->all();
            if (count($existing) !== count($keys)) {
                $missing = array_diff($keys, $existing);
                return response()->json(['message' => 'Some subindikator_id not found', 'missing' => array_values($missing)], 422);
            }

            $penilaian = [];
            foreach ($data['penilaian'] as $k => $entry) {
                $penilaian[$k] = [
                    'nilai' => round((float) ($entry['nilai'] ?? 0), 2),
                    'hasil' => round((float) ($entry['hasil'] ?? 0), 2),
                ];
            }
            $record->penilaian = $penilaian;
        }
        if (isset($data['pegawai_id'])) {
            $record->pegawai_id = $data['pegawai_id'];
        }

        $record->save();

        return response()->json($record);
    }

    public function destroy($id)
    {
        $record = Penilaian::findOrFail($id);
        $record->delete();
        return response()->json(null, 204);
    }

    /**
     * Bulk upload penilaian via Excel or CSV.
     * Expected columns: nip, <subindikator name 1>, <subindikator name 2>, ...
     */
    public function bulk(Request $request)
    {
        // Support JSON payload from BE: { "penilaians": [ { "_rowIndex":2, "nip":"...", "nama":"...", "Sub A":"", "Sub B":"1.0" } ] }
        if ($request->has('penilaians')) {
            $rows = $request->get('penilaians');
            if (!is_array($rows) || count($rows) === 0) {
                return response()->json(['message' => 'penilaians must be a non-empty array'], 422);
            }

            // Determine subindikator column names. Prefer explicit `headers` array from payload.
            $metaKeys = ['_rowIndex', 'nip', 'nama'];
            $metaKeysLower = array_map('strtolower', $metaKeys);

            if ($request->has('headers') && is_array($request->get('headers'))) {
                $headers = array_map(function ($h) {
                    return trim((string) $h);
                }, $request->get('headers'));

                // require at least 'nip' present in headers
                $lowerHeaders = array_map('strtolower', $headers);
                if (!in_array('nip', $lowerHeaders, true)) {
                    return response()->json(['message' => "Headers must include 'nip' column"], 422);
                }

                $subNames = array_values(array_filter($headers, function ($h) use ($metaKeysLower) {
                    return !in_array(strtolower($h), $metaKeysLower, true);
                }));
            } else {
                $first = $rows[0];
                if (!is_array($first)) {
                    return response()->json(['message' => 'Invalid row format'], 422);
                }

                $subNames = array_values(array_filter(array_keys($first), function ($k) use ($metaKeysLower) {
                    return !in_array(strtolower($k), $metaKeysLower, true);
                }));
            }

            if (count($subNames) === 0) {
                return response()->json(['message' => 'No subindikator columns found in payload'], 422);
            }

            // Load subindikators with indikator relation and build lookup by normalized name
            $allSub = SubIndikator::with('indikator')->get();
            $subMap = [];
            foreach ($allSub as $s) {
                $key = $this->normalizeName($s->subindikator ?? null);
                if ($key === '') {
                    continue;
                }
                $subMap[$key] = $s; // store model for later use (bobot, indikator)
            }

            // Load all standar kompetensi into map [jenis_jabatan_id][subindikator_id] => standar
            $allStandar = StandarKompetensiMsk::all();
            $standarMap = [];
            foreach ($allStandar as $st) {
                $jid = $st->jenis_jabatan_id;
                $sid = $st->subindikator_id;
                $standarMap[$jid][$sid] = (float) $st->standar;
            }

            $missingCols = [];
            foreach ($subNames as $sn) {
                $key = $this->normalizeName($sn);
                if (!isset($subMap[$key])) {
                    $missingCols[] = $sn;
                }
            }
            if (count($missingCols) > 0) {
                return response()->json(['message' => 'Some subindikator columns not found', 'missing' => array_values($missingCols)], 422);
            }

            $created = 0;
            $failed = [];
            DB::beginTransaction();
            try {
                foreach ($rows as $r) {
                    $rowIndex = $r['_rowIndex'] ?? null;
                    $nip = isset($r['nip']) ? trim((string) $r['nip']) : '';
                    if ($nip === '') {
                        $failed[] = ['row' => $rowIndex, 'reason' => 'Empty NIP'];
                        continue;
                    }

                    $pegawai = Pegawai::where('nip', $nip)->first();
                    if (!$pegawai) {
                        $failed[] = ['row' => $rowIndex, 'nip' => $nip, 'reason' => 'Pegawai not found'];
                        continue;
                    }

                    $penilaian = [];
                    $rowError = null;
                    foreach ($subNames as $sn) {
                        $value = array_key_exists($sn, $r) ? $r[$sn] : '';
                        // Skip empty values instead of throwing error - allow partial updates
                        if (is_null($value) || $value === '') {
                            continue;
                        }
                        if (!is_numeric($value)) {
                            $rowError = "Non-numeric value for '{$sn}'";
                            break;
                        }

                        $subKey = $this->normalizeName($sn);
                        $sub = $subMap[$subKey] ?? null;
                        if (!$sub) {
                            $rowError = "Unknown subindikator for '{$sn}'";
                            break;
                        }

                        $subId = $sub->id;
                        $nilai = (float) $value;
                        $bobot = (float) ($sub->bobot ?? 0);

                        // Determine if this indikator uses standar-based calculation
                        $usesStandarMsk = $sub->indikator->indikator === 'Penilaian Kompetensi Manajerial dan Sosial Kultural';
                        $usesStandarPotensi = $sub->indikator->indikator === 'Penilaian Potensi Talenta';

                        $hasil = 0.0;
                        if ($usesStandarMsk) {
                            $jid = $pegawai->jenis_jabatan_id ?? null;
                            $standar = $jid && isset($standarMap[$jid][$subId]) ? (float) $standarMap[$jid][$subId] : 0.0;
                            if ($standar > 0) {
                                $hasil = ($nilai / $standar) * 100.0 * ($bobot / 100.0);
                            } else {
                                $hasil = 0.0;
                            }
                        } elseif ($usesStandarPotensi) {
                            $hasil = ($nilai / 5) * 100.0 * ($bobot / 100.0);
                        } else {
                            $hasil = $nilai * ($bobot / 100.0);
                        }

                        $penilaian[$subId] = [
                            'nilai' => round($nilai, 2),
                            'hasil' => round($hasil, 2),
                        ];
                    }

                    if ($rowError) {
                        $failed[] = ['row' => $rowIndex, 'nip' => $nip, 'reason' => $rowError];
                        continue;
                    }

                    // Skip this row if no valid penilaian data was found
                    if (count($penilaian) === 0) {
                        $failed[] = ['row' => $rowIndex, 'nip' => $nip, 'reason' => 'No valid subindikator values provided'];
                        continue;
                    }

                    // Upsert by pegawai_id when available
                    $pegawaiId = $pegawai->id ?? null;
                    if ($pegawaiId) {
                        $existing = Penilaian::where('pegawai_id', $pegawaiId)->first();
                    } else {
                        $existing = null;
                    }

                    if ($existing) {
                        // Merge with existing penilaian data instead of replacing
                        $existingPenilaian = is_array($existing->penilaian) ? $existing->penilaian : [];
                        $existing->penilaian = array_merge($existingPenilaian, $penilaian);
                        $existing->pegawai_id = $pegawai->id;
                        $existing->save();
                    } else {
                        $rec = new Penilaian();
                        $rec->pegawai_id = $pegawai->id;
                        $rec->penilaian = $penilaian;
                        $rec->save();
                    }
                    $created++;
                }
                DB::commit();
                return response()->json(['success' => true, 'created' => $created, 'failed' => $failed], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
            }
        }

        // Fallback: accept uploaded spreadsheet file
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid file upload', 'errors' => $validator->errors()], 422);
        }

        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();

            $highestRow = (int) $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            if ($highestRow < 2 || $highestColumnIndex < 2) {
                return response()->json(['message' => 'Spreadsheet must contain header row and at least one data row and one subindikator column'], 422);
            }

            // Read header row
            $headers = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $h = trim((string) $sheet->getCell([$col, 1])->getValue());
                $headers[] = $h;
            }

            if (count($headers) < 2 || strtolower($headers[0]) !== 'nip') {
                return response()->json(['message' => "First column must be 'nip'"], 422);
            }

            $subNames = array_slice($headers, 1);

            // Build lookup for subindikator name -> model (normalized)
            $allSub = SubIndikator::with('indikator')->get();
            $subMap = [];
            foreach ($allSub as $s) {
                $key = $this->normalizeName($s->subindikator ?? null);
                if ($key === '') {
                    continue;
                }
                $subMap[$key] = $s;
            }

            // Load all standar kompetensi into map [jenis_jabatan_id][subindikator_id] => standar
            $allStandar = StandarKompetensiMsk::all();
            $standarMap = [];
            foreach ($allStandar as $st) {
                $jid = $st->jenis_jabatan_id;
                $sid = $st->subindikator_id;
                $standarMap[$jid][$sid] = (float) $st->standar;
            }

            $missingCols = [];
            foreach ($subNames as $sn) {
                $key = $this->normalizeName($sn);
                if (!isset($subMap[$key])) {
                    $missingCols[] = $sn;
                }
            }

            if (count($missingCols) > 0) {
                return response()->json(['message' => 'Some subindikator columns not found', 'missing' => array_values($missingCols)], 422);
            }

            $created = 0;
            $failed = [];

            DB::beginTransaction();
            for ($row = 2; $row <= $highestRow; $row++) {
                $nip = trim((string) $sheet->getCell([1, $row])->getValue());
                if ($nip === '') {
                    $failed[] = ['row' => $row, 'reason' => 'Empty NIP'];
                    continue;
                }

                $pegawai = Pegawai::where('nip', $nip)->first();
                if (!$pegawai) {
                    $failed[] = ['row' => $row, 'nip' => $nip, 'reason' => 'Pegawai not found'];
                    continue;
                }

                $penilaian = [];
                $rowError = null;
                    for ($c = 0; $c < count($subNames); $c++) {
                    $colIndex = $c + 2; // column 2 onwards
                    $value = $sheet->getCell([$colIndex, $row])->getCalculatedValue();
                    $value = is_null($value) ? '' : trim((string) $value);

                    // Skip empty values instead of throwing error - allow partial updates
                    if ($value === '') {
                        continue;
                    }

                    if (!is_numeric($value)) {
                        $rowError = "Non-numeric value for '{$subNames[$c]}'";
                        break;
                    }

                    $subKey = $this->normalizeName($subNames[$c]);
                    $sub = $subMap[$subKey] ?? null;
                    if (!$sub) {
                        $rowError = "Unknown subindikator for '{$subNames[$c]}'";
                        break;
                    }

                    $subId = $sub->id;
                    $nilai = (float) $value;
                    $bobot = (float) ($sub->bobot ?? 0);
                    $usesStandarMsk = $sub->indikator->indikator === 'Penilaian Kompetensi Manajerial dan Sosial Kultural';
                    $usesStandarPotensi = $sub->indikator->indikator === 'Penilaian Potensi Talenta';

                    $hasil = 0.0;
                    if ($usesStandarMsk) {
                        $jid = $pegawai->jenis_jabatan_id ?? null;
                        $standar = $jid && isset($standarMap[$jid][$subId]) ? (float) $standarMap[$jid][$subId] : 0.0;
                        if ($standar > 0) {
                            $hasil = ($nilai / $standar) * 100.0 * ($bobot / 100.0);
                        } else {
                            $hasil = 0.0;
                        }
                    } elseif ($usesStandarPotensi) {
                        $hasil = ($nilai / 5) * 100.0 * ($bobot / 100.0);
                    } else {
                        $hasil = $nilai * ($bobot / 100.0);
                    }

                    $penilaian[$subId] = [
                        'nilai' => round($nilai, 2),
                        'hasil' => round($hasil, 2),
                    ];
                }

                if ($rowError) {
                    $failed[] = ['row' => $row, 'nip' => $nip, 'reason' => $rowError];
                    continue;
                }

                // Skip this row if no valid penilaian data was found
                if (count($penilaian) === 0) {
                    $failed[] = ['row' => $row, 'nip' => $nip, 'reason' => 'No valid subindikator values provided'];
                    continue;
                }

                // Upsert by pegawai_user_id when available
                $userKey = $pegawai->pegawai_id ?? null;
                if ($userKey) {
                    $existing = Penilaian::where('pegawai_user_id', $userKey)->first();
                } else {
                    $existing = null;
                }

                if ($existing) {
                    // Merge with existing penilaian data instead of replacing
                    $existingPenilaian = is_array($existing->penilaian) ? $existing->penilaian : [];
                    $existing->penilaian = array_merge($existingPenilaian, $penilaian);
                    $existing->pegawai_id = $pegawai->id;
                    $existing->save();
                } else {
                    $rec = new Penilaian();
                    $rec->pegawai_id = $pegawai->id;
                    if ($userKey) {
                        $rec->pegawai_user_id = $userKey;
                    }
                    $rec->penilaian = $penilaian;
                    $rec->save();
                }
                $created++;
            }
            DB::commit();

            return response()->json(['success' => true, 'created' => $created, 'failed' => $failed], 200);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            return response()->json(['message' => 'Failed to parse spreadsheet', 'error' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
        }
    }
}
