<?php

namespace App\Http\Controllers;

use App\Models\Statistik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class StatistikController extends Controller
{
    /**
     * Manually trigger statistik update
     */
    public function sync()
    {
        // Set time limit for operation
        set_time_limit(300);
        
        try {
            Artisan::call('update:statistik');
            $output = Artisan::output();

            // Extract summary from output
            $output = str_replace("\r", '', $output);
            
            $completed = strpos($output, 'successfully!') !== false;

            return response()->json([
                'success' => true,
                'message' => $completed ? 'Statistik refreshed successfully!' : 'Statistik refresh triggered',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh statistik',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all statistics (key-value pairs)
     */
    public function index()
    {
        try {
            $statistik = Statistik::orderBy('key')->get();

            // Transform to simple key-value array
            $data = $statistik->mapWithKeys(function ($item) {
                return [$item->key => $item->value];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistik',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
