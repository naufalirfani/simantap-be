<?php

namespace App\Http\Controllers;

use App\Services\UmpanBalik360Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        ini_set('memory_limit', '512M');

        try {
            $startTime = microtime(true);
            $result = $this->service->syncFromNusaApi();
            $duration = round(microtime(true) - $startTime, 2);

            $statusCode = ($result['success'] ?? false) ? 200 : 500;

            if ($statusCode === 200) {
                Log::info("UmpanBalik360 sync successfully executed in {$duration}s", [
                    'updated_count' => $result['updated_count'] ?? 0,
                    'not_found_count' => count($result['not_found_nips'] ?? []),
                ]);
            } else {
                Log::warning("UmpanBalik360 sync finished with failure in {$duration}s", [
                    'message' => $result['message'] ?? '',
                ]);
            }

            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('UmpanBalik360 sync controller error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan sinkronisasi Umpan Balik 360.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
