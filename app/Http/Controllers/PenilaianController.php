<?php

namespace App\Http\Controllers;

use App\Jobs\DispatchSyncPenilaianJob;
use App\Models\Pegawai;
use App\Models\Penilaian;
use App\Models\RiwayatAsesmen;
use App\Models\StandarKompetensiMsk;
use App\Models\SubIndikator;
use App\Services\PenilaianSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PenilaianController extends Controller
{
    public function __construct(private readonly PenilaianSyncService $syncService) {}

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
     * Monitor sync penilaian progress.
     * GET /api/penilaians/sync-status
     *
     * Optional params:
     *   nip (string|string[]) – restrict status to specific NIP(s), same as sync endpoint
     *
     * Returns:
     *   total          – total pegawai in scope
     *   synced         – pegawai with last_sync_penilaian not null
     *   pending        – pegawai with last_sync_penilaian null
     *   last_sync_at   – most recent last_sync_penilaian timestamp
     *   oldest_sync_at – oldest last_sync_penilaian timestamp (non-null)
     *   queue_pending  – number of SyncPenilaianBatchJob entries still in the jobs table
     *   filter_nips         – nip filter applied, or null if all pegawai
     *   session_dispatched_at – when the latest sync was triggered
     *   session_total_nips    – total NIPs in the latest sync session
     *   session_total_batches – total batch jobs dispatched in the latest sync
     *   session_synced        – NIPs completed since the latest sync was triggered
     *   session_pending       – NIPs not yet completed in the latest sync
     *   queue_pending         – batch jobs still waiting in the queue
     *   queue_completed       – batch jobs already processed in the latest sync
     */
    public function syncStatus(Request $request)
    {
        try {
            $filterNips = null;
            if ($request->filled('nip')) {
                $raw        = $request->input('nip');
                $filterNips = is_array($raw) ? array_map('strval', $raw) : [strval($raw)];
            }

            $baseQuery = fn () => $filterNips !== null
                ? Pegawai::whereIn('nip', $filterNips)
                : Pegawai::query();

            $total        = $baseQuery()->count();
            $lastSyncAt   = $baseQuery()->whereNotNull('last_sync_penilaian')->max('last_sync_penilaian');
            $oldestSyncAt = $baseQuery()->whereNotNull('last_sync_penilaian')->min('last_sync_penilaian');

            // Current sync session metadata (stored by DispatchSyncPenilaianJob)
            $session             = DB::table('penilaian_sync_sessions')->latest('id')->first();
            $sessionDispatchedAt = $session->dispatched_at ?? null;
            $sessionTotalNips    = $session->total_nips ?? null;
            $sessionTotalBatches = $session->total_batches ?? null;

            // Progress within current session: count NIPs synced AFTER the session started
            $sessionSynced  = null;
            $sessionPending = null;
            if ($sessionDispatchedAt) {
                $sessionSynced  = $baseQuery()->where('last_sync_penilaian', '>=', $sessionDispatchedAt)->count();
                $sessionPending = ($sessionTotalNips ?? $total) - $sessionSynced;
            }

            // Queue job progress
            $queuePending   = null;
            $queueCompleted = null;
            try {
                $queuePending = DB::table('jobs')
                    ->where('payload', 'like', '%SyncPenilaianBatchJob%')
                    ->count();
                if ($sessionTotalBatches !== null) {
                    $queueCompleted = $sessionTotalBatches - $queuePending;
                }
            } catch (\Exception $e) {
                // Queue table may not exist when using non-database driver
            }

            return response()->json([
                'total'                => $total,
                'last_sync_at'         => $lastSyncAt,
                'oldest_sync_at'       => $oldestSyncAt,
                'filter_nips'          => $filterNips,
                'session_dispatched_at'=> $sessionDispatchedAt,
                'session_total_nips'   => $sessionTotalNips,
                'session_total_batches'=> $sessionTotalBatches,
                'session_synced'       => $sessionSynced,
                'session_pending'      => $sessionPending,
                'queue_pending'        => $queuePending,
                'queue_completed'      => $queueCompleted,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Recalculate and sync penilaian records (runs in background via queue).
     * POST /api/penilaians/sync
     *
     * Optional params:
     *   nip (string|string[]) – restrict sync to specific NIP(s)
     *
     * When > 100 pegawai are involved the work is automatically split into
     * batches of 100, each processed by a separate queued job.
     * Returns 202 Accepted immediately.
     */
    public function sync(Request $request)
    {
        try {
            $filterNips = null;
            if ($request->filled('nip')) {
                $raw        = $request->input('nip');
                $filterNips = is_array($raw) ? array_map('strval', $raw) : [strval($raw)];
            }

            // Insert sync session row so syncStatus can track current-session progress
            DB::table('penilaian_sync_sessions')->insert([
                'dispatched_at' => now(),
                'total_nips'    => null,
                'total_batches' => null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DispatchSyncPenilaianJob::dispatch($filterNips);

            return response()->json([
                'success' => true,
                'message' => 'Sync job dispatched. Processing will run in the background.',
            ], 202);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to dispatch sync job', 'error' => $e->getMessage()], 500);
        }
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
        $isAsesmen = $request->boolean('isAsesmen', false);
        $namaAsesmen = trim((string) $request->input('nama_asesmen', ''));
        $asesmenIndikatorNames = [
            'Penilaian Kompetensi Manajerial dan Sosial Kultural',
            'Penilaian Potensi Talenta',
        ];

        if ($isAsesmen && $namaAsesmen === '') {
            return response()->json(['message' => 'nama_asesmen is required when isAsesmen=true'], 422);
        }

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
                $key = $this->syncService->normalizeName($s->subindikator ?? null);
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
                $key = $this->syncService->normalizeName($sn);
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
                    $nip = trim((string) (array_change_key_case($r, CASE_LOWER)['nip'] ?? ''));
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

                        $subKey = $this->syncService->normalizeName($sn);
                        $sub = $subMap[$subKey] ?? null;
                        if (!$sub) {
                            $rowError = "Unknown subindikator for '{$sn}'";
                            break;
                        }

                        $subId = $sub->id;
                        $nilai = (float) $value;
                        $bobot = (float) ($sub->bobot ?? 0);

                        $indikatorName = $sub->indikator->indikator ?? null;
                        if (in_array($indikatorName, $asesmenIndikatorNames, true) && !$isAsesmen) {
                            continue;
                        }

                        // Determine if this indikator uses standar-based calculation
                        $usesStandarMsk     = $sub->indikator->indikator === 'Penilaian Kompetensi Manajerial dan Sosial Kultural';
                        $usesStandarPotensi = $sub->indikator->indikator === 'Penilaian Potensi Talenta';

                        $jid     = $pegawai->jenis_jabatan_id ?? null;
                        $standar = ($usesStandarMsk && $jid && isset($standarMap[$jid][$subId]))
                            ? (float) $standarMap[$jid][$subId] : null;
                        $hasil = $this->syncService->computeHasil($nilai, $bobot, $usesStandarMsk, $usesStandarPotensi, $standar);

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

                    if ($isAsesmen) {
                        RiwayatAsesmen::create([
                            'nama_asesmen' => $namaAsesmen,
                            'pegawai_id' => $pegawai->id,
                            'data_asesmen' => $penilaian,
                        ]);
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
                $key = $this->syncService->normalizeName($s->subindikator ?? null);
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
                $key = $this->syncService->normalizeName($sn);
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

                    $subKey = $this->syncService->normalizeName($subNames[$c]);
                    $sub = $subMap[$subKey] ?? null;
                    if (!$sub) {
                        $rowError = "Unknown subindikator for '{$subNames[$c]}'";
                        break;
                    }

                    $subId = $sub->id;
                    $nilai = (float) $value;
                    $bobot = (float) ($sub->bobot ?? 0);

                    $indikatorName = $sub->indikator->indikator ?? null;
                    if (in_array($indikatorName, $asesmenIndikatorNames, true) && !$isAsesmen) {
                        continue;
                    }

                    $usesStandarMsk     = $sub->indikator->indikator === 'Penilaian Kompetensi Manajerial dan Sosial Kultural';
                    $usesStandarPotensi = $sub->indikator->indikator === 'Penilaian Potensi Talenta';

                    $jid     = $pegawai->jenis_jabatan_id ?? null;
                    $standar = ($usesStandarMsk && $jid && isset($standarMap[$jid][$subId]))
                        ? (float) $standarMap[$jid][$subId] : null;
                    $hasil = $this->syncService->computeHasil($nilai, $bobot, $usesStandarMsk, $usesStandarPotensi, $standar);

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

                if ($isAsesmen) {
                    RiwayatAsesmen::create([
                        'nama_asesmen' => $namaAsesmen,
                        'pegawai_id' => $pegawai->id,
                        'data_asesmen' => $penilaian,
                    ]);
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
