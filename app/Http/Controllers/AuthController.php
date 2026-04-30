<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Login admin with email and password from .env
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        // Validate input
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $request->input('email');
        $password = $request->input('password');

        // Get credentials from environment
        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');

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
