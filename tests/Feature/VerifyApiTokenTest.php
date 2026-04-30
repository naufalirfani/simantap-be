<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyApiToken;
use Illuminate\Http\Request;
use Tests\TestCase;

class VerifyApiTokenTest extends TestCase
{
    public function test_plaintext_token_is_rejected(): void
    {
        config()->set('app.api_tokens', ['secret-token']);

        $middleware = new VerifyApiToken();
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-API-TOKEN', 'secret-token');

        $response = $middleware->handle($request, static function () {
            return response()->json(['success' => true]);
        });

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized: Invalid API token.',
        ]);
    }
}
