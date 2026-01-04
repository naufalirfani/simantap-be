<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyApiToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $provided = $request->header('X-API-TOKEN') ?? $request->query('api_token');
        $provided = is_string($provided) ? trim($provided) : $provided;
        $expected = env('API_TOKEN');

        if (empty($expected) || !is_string($expected)) {
            return response()->json([
                'success' => false,
                'message' => 'API token not configured on server.'
            ], 500);
        }

        if (!$provided) {
            return response()->json([
                'success' => false,
                'message' => 'Missing API token. Please provide X-API-TOKEN header.'
            ], 401);
        }

        if (!hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: invalid API token.'
            ], 401);
        }

        return $next($request);
    }
}
