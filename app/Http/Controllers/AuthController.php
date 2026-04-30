<?php

namespace App\Http\Controllers;

use App\Support\DecryptsEncryptedToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use DecryptsEncryptedToken;

    /**
     * Login admin with email and password from .env
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        // Get credentials from environment
        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');

        $email = $this->normalizeCredential($request->input('email'), $adminEmail);
        $password = $this->normalizeCredential($request->input('password'), $adminPassword);

        if ($email === null || $password === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to decrypt email or password.',
            ], 401);
        }

        // Validate input after decrypting encrypted payloads
        $request->merge([
            'email' => $email,
            'password' => $password,
        ]);

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Check if credentials match
        if ($email === $adminEmail && $password === $adminPassword) {
            // Generate a token or session
            $token = base64_encode(json_encode([
                'email' => $adminEmail,
                'timestamp' => now()->timestamp,
                'type' => 'admin',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'email' => $adminEmail,
                    'token' => $token,
                    'type' => 'admin',
                ],
            ], 200);
        }

        // Invalid credentials
        return response()->json([
            'success' => false,
            'message' => 'Invalid email or password',
        ], 401);
    }

    private function normalizeCredential(?string $value, ?string $expectedValue): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (strpos($value, 'v1.aes:') !== 0) {
            return $value;
        }

        if (empty($expectedValue)) {
            return null;
        }

        return $this->decryptEncryptedToken($value, [$expectedValue]);
    }

    /**
     * Logout admin
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ], 200);
    }
}
