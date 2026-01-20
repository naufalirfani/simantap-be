<?php

namespace App\Http\Controllers;

use App\Models\PetaJabatan;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class PetaJabatanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Read withPagination flag (default true)
            $withPagination = filter_var($request->get('with_pagination', 'true'), FILTER_VALIDATE_BOOLEAN);
            // Read jabatan_kosong filter flag (default false) and detect if param present
            $jabatanKosong = filter_var($request->get('jabatan_kosong', 'false'), FILTER_VALIDATE_BOOLEAN);
            $hasJabatanKosongParam = $request->has('jabatan_kosong');
            $query = PetaJabatan::orderBy('order_index')->orderBy('level');
            $perPage = max(1, (int) $request->get('per_page', 10));
            $page = max(1, (int) $request->get('page', 1));

            // Load all models first so we can filter by pejabat (JSON) when requested
            $models = $query->get()->all();

            // parse pejabat into arrays but do NOT enrich here; enrichment only shown when
            // the `jabatan_kosong` parameter is present (hasJabatanKosongParam)
            $items = array_map(function ($model) {
                $a = is_array($model) ? $model : $model->toArray();
                $pej = $a['pejabat'] ?? null;
                if (is_array($pej)) {
                    $pejArray = $pej;
                } elseif (is_string($pej)) {
                    $pejArray = json_decode($pej, true) ?: [];
                } else {
                    $pejArray = [];
                }

                $a['pejabat'] = $pejArray;
                return $a;
            }, $models);

            // If jabatan_kosong param provided, filter items accordingly (only focus on selected ESELON types)
            if ($hasJabatanKosongParam) {
                $allowedTypes = [
                    'ESELON I / JPT MADYA',
                    'ESELON II / JPT PRATAMA',
                    'ESELON III / ADMINISTRATOR',
                    'ESELON IV / PENGAWAS',
                ];

                $items = array_values(array_filter($items, function ($a) use ($allowedTypes, $jabatanKosong) {
                    $jenis = strtoupper(trim($a['jenis_jabatan'] ?? ''));

                    // Only consider the allowed ESELON types; others are not considered vacant
                    if (!in_array($jenis, $allowedTypes, true)) {
                        $isVacant = false;
                        return $jabatanKosong ? $isVacant : !$isVacant;
                    }

                    $bezetting = isset($a['bezetting']) ? (int) $a['bezetting'] : 0;
                    $kebutuhan = isset($a['kebutuhan_pegawai']) ? (int) $a['kebutuhan_pegawai'] : 0;

                    if ($bezetting === 0) {
                        $isVacant = true;
                        return $jabatanKosong ? $isVacant : !$isVacant;
                    }

                    if (($kebutuhan - $bezetting) > 0) {
                        $isVacant = true;
                        return $jabatanKosong ? $isVacant : !$isVacant;
                    }

                    $pej = $a['pejabat'] ?? [];
                    if (!is_array($pej) || count($pej) === 0) {
                        $isVacant = true;
                        return $jabatanKosong ? $isVacant : !$isVacant;
                    }

                    // pick the pejabat with largest numeric NIP when multiple
                    $selected = null;
                    foreach ($pej as $p) {
                        $nip = isset($p['nip']) ? preg_replace('/[^0-9]/', '', $p['nip']) : '';
                        if ($nip === '') continue;
                        if ($selected === null) {
                            $selected = $nip;
                        } elseif (strcmp($nip, $selected) > 0) {
                            $selected = $nip;
                        }
                    }

                    if ($selected === null || strlen($selected) < 8) {
                        $isVacant = false;
                        return $jabatanKosong ? $isVacant : !$isVacant;
                    }

                    $retirementAge = in_array($jenis, ['ESELON I / JPT MADYA', 'ESELON II / JPT PRATAMA'], true) ? 60 : 58;

                    $dobStr = substr($selected, 0, 8); // YYYYMMDD
                    try {
                        $dob = Carbon::createFromFormat('Ymd', $dobStr);
                    } catch (\Exception $e) {
                        $isVacant = false;
                        return $jabatanKosong ? $isVacant : !$isVacant;
                    }

                    $retirementDate = $dob->copy()->addYears($retirementAge);
                    // vacant if retirement date is within next 1 year
                    $isVacant = $retirementDate->lte(Carbon::now()->addYear());

                    return $jabatanKosong ? $isVacant : !$isVacant;
                }));
                // after filtering, enrich each pejabat entry with tglLahir, tanggal_pensiun and sisa_masa_kerja
                foreach ($items as &$a) {
                    $pejArray = $a['pejabat'] ?? [];
                    $jenis = strtoupper(trim($a['jenis_jabatan'] ?? ''));
                    $retirementAge = in_array($jenis, ['ESELON I / JPT MADYA', 'ESELON II / JPT PRATAMA'], true) ? 60 : 58;

                    foreach ($pejArray as &$p) {
                        $p = is_array($p) ? $p : (is_object($p) ? (array)$p : ['nama' => (string)$p]);
                        $nipRaw = isset($p['nip']) ? (string)$p['nip'] : '';
                        $nipDigits = preg_replace('/[^0-9]/', '', $nipRaw);
                        $p['tglLahir'] = null;
                        $p['tanggal_pensiun'] = null;
                        $p['sisa_masa_kerja'] = null;
                        $p['sisa_masa_kerja_days'] = null;

                        if (strlen($nipDigits) >= 8) {
                            $dobStr = substr($nipDigits, 0, 8); // YYYYMMDD
                            try {
                                $dob = Carbon::createFromFormat('Ymd', $dobStr);
                                $p['tglLahir'] = $dob->toDateString();
                                $retirementDate = $dob->copy()->addYears($retirementAge);
                                $p['tanggal_pensiun'] = $retirementDate->toDateString();
                                if ($retirementDate->lte(Carbon::now())) {
                                    $p['sisa_masa_kerja'] = '0 tahun 0 bulan';
                                    $p['sisa_masa_kerja_days'] = 0;
                                } else {
                                    $diff = Carbon::now()->diff($retirementDate);
                                    $p['sisa_masa_kerja'] = sprintf('%d tahun %d bulan', $diff->y, $diff->m);
                                    $p['sisa_masa_kerja_days'] = Carbon::now()->diffInDays($retirementDate);
                                }
                            } catch (\Exception $e) {
                                // leave nulls if date parse fails
                            }
                        }
                    }

                    $a['pejabat'] = $pejArray;
                }
            }

            $response = [
                'success' => true,
            ];

            if ($withPagination) {
                $total = count($items);
                $offset = ($page - 1) * $perPage;
                $itemsForPage = array_slice($items, $offset, $perPage);

                $paginator = new LengthAwarePaginator($itemsForPage, $total, $perPage, $page, [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'query' => $request->query(),
                ]);

                $response['data'] = $itemsForPage;
                $response['meta'] = [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ];
            } else {
                $response['data'] = $items;
            }

            return response()->json($response, 200);
        } catch (\Throwable $e) {
            Log::error('PetaJabatanController@index failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat mengambil data peta jabatan.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manual sync trigger endpoint
     */
    public function sync()
    {
        try {
            Log::info('Manual peta jabatan sync triggered');

            // Run the sync command
            $exitCode = Artisan::call('sync:peta-jabatan');

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sinkronisasi peta jabatan berhasil dilakukan',
                    'data' => [
                        'synced_at' => now()->toDateTimeString()
                    ]
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Sinkronisasi gagal. Silakan cek log untuk detail.',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Manual peta jabatan sync failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat sinkronisasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $petaJabatan = PetaJabatan::find($id);

        if (!$petaJabatan) {
            return response()->json([
                'success' => false,
                'message' => 'Peta jabatan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $petaJabatan
        ], 200);
    }

    /**
     * Get hierarchy tree
     */
    public function tree()
    {
        $allJabatan = PetaJabatan::orderBy('level')
            ->orderBy('order_index')
            ->get();

        $tree = $this->buildTree($allJabatan);

        return response()->json([
            'success' => true,
            'data' => $tree
        ], 200);
    }

    /**
     * Get hierarchy tree unique by unit_kerja
     * Returns nodes with only id, parent_id and unit_kerja
     */
    public function treeByUnitKerja()
    {
        $all = PetaJabatan::orderBy('level')->orderBy('order_index')->get();

        // Normalize to simple arrays
        $allArr = [];
        foreach ($all as $m) {
            $allArr[] = [
                'id' => $m->id,
                'parent_id' => $m->parent_id,
                'unit_kerja' => $m->unit_kerja,
            ];
        }

        // Map of original id -> item
        $byId = [];
        foreach ($allArr as $a) {
            $byId[$a['id']] = $a;
        }

        // Build unique set keyed by unit_kerja (first occurrence wins)
        $unitMap = [];
        $uniqueItems = [];
        foreach ($allArr as $a) {
            $uk = trim((string)($a['unit_kerja'] ?? ''));
            if ($uk === '') {
                $uk = '__empty__' . $a['id'];
            }
            if (!isset($unitMap[$uk])) {
                $unitMap[$uk] = $a;
                $uniqueItems[$a['id']] = $a;
            }
        }

        // Remap parent_id to representative ids (by unit_kerja)
        $final = [];
        foreach ($uniqueItems as $id => $item) {
            $origPid = $item['parent_id'];
            $newPid = null;
            if ($origPid !== null) {
                if (isset($byId[$origPid])) {
                    $parentUnit = trim((string)($byId[$origPid]['unit_kerja'] ?? ''));
                    if ($parentUnit === '') {
                        $parentUnit = '__empty__' . $byId[$origPid]['id'];
                    }
                    if (isset($unitMap[$parentUnit])) {
                        $newPid = $unitMap[$parentUnit]['id'];
                        if ($newPid === $item['id']) {
                            $newPid = null;
                        }
                    }
                }
            }

            $final[$item['id']] = [
                'id' => $item['id'],
                'parent_id' => $newPid,
                'unit_kerja' => $item['unit_kerja'],
                'children' => [],
            ];
        }

        // Build tree from remapped final items
        $roots = [];
        foreach ($final as $id => &$node) {
            if ($node['parent_id'] === null) {
                $roots[] = &$node;
            } else {
                $pid = $node['parent_id'];
                if (isset($final[$pid])) {
                    $final[$pid]['children'][] = &$node;
                } else {
                    $roots[] = &$node;
                }
            }
        }

        // Clean references and return
        $result = array_values($roots);

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200);
    }

    /**
     * Build hierarchical tree structure
     */
    private function buildTree($items, $parentId = null)
    {
        $branch = [];

        foreach ($items as $item) {
            if ($item->parent_id == $parentId) {
                $children = $this->buildTree($items, $item->id);
                if ($children) {
                    $item->children = $children;
                }
                $branch[] = $item;
            }
        }

        return $branch;
    }
}
