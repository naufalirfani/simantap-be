<?php

namespace App\Http\Middleware;

use App\Support\DecryptsEncryptedToken;
use Closure;
use Illuminate\Http\Request;

class VerifyApiToken
{
    use DecryptsEncryptedToken;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $apiToken = $request->header('X-API-TOKEN') ?? $request->query('api_token');
        $validTokens = config('app.api_tokens', []);

        // If token is encrypted (client prefix: v1.aes:), try decrypting
        if (!empty($apiToken) && strpos($apiToken, 'v1.aes:') === 0) {
            $decrypted = $this->decryptEncryptedToken($apiToken, $validTokens);
            if (!empty($decrypted)) {
                $apiToken = $decrypted;
            }
        }

        if (empty($validTokens)) {
            return response()->json([
                'success' => false,
                'message' => 'API token not configured on server.'
            ], 500);
        }

        if (empty($apiToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing API token. Please provide X-API-TOKEN header.'
            ], 401);
        }

        if (!in_array($apiToken, $validTokens)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: invalid API token.'
            ], 401);
        }

        return $next($request);
    }
}
