<?php

namespace App\Http\Controllers;

use App\Services\UmpanBalik360Service;
use Illuminate\Http\Request;

class UmpanBalik360Controller extends Controller
{
    protected UmpanBalik360Service $service;

    public function __construct(UmpanBalik360Service $service)
    {
        $this->service = $service;
    }

    /**
     * Trigger sync/pull of 360 Feedback data from external NUSA API and store recap in DB.
     */
    public function sync(Request $request)
    {
        set_time_limit(600);

        try {
            $result = $this->service->syncFromNusaApi();
            $statusCode = ($result['success'] ?? false) ? 200 : 500;

            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan sinkronisasi Umpan Balik 360.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
