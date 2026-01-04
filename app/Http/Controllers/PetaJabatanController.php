<?php

namespace App\Http\Controllers;

use App\Models\PetaJabatan;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class PetaJabatanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $perPage = max(1, (int) request()->get('per_page', 10));
            $page = max(1, (int) request()->get('page', 1));

            $query = PetaJabatan::orderBy('order_index')->orderBy('level');

            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            $items = array_map(function ($model) {
                $a = is_array($model) ? $model : $model->toArray();
                $np = $a['nama_pejabat'] ?? null;
                if (is_array($np)) {
                    $a['nama_pejabat'] = $np;
                } elseif (is_string($np)) {
                    $a['nama_pejabat'] = json_decode($np, true) ?: [];
                } else {
                    $a['nama_pejabat'] = [];
                }
                return $a;
            }, $paginator->items());

            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                ]
            ], 200);
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
