<?php

namespace Tests\Feature;

use App\Services\TokenEncryptionService;
use App\Services\UmpanBalik360Service;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UmpanBalik360SyncTest extends TestCase
{
    public function test_sync_endpoint_returns_success(): void
    {
        $token = 'test-token';
        config()->set('app.api_tokens', [$token]);
        config()->set('cors.allowed_origins', ['https://simantap.dpd.go.id']);
        $encryptedToken = TokenEncryptionService::encryptTokenForHeader($token, ['salt' => $token]);

        // Mock the service so we test the controller endpoint contract without depending on external network
        $mockService = $this->createMock(UmpanBalik360Service::class);
        $mockService->method('syncFromNusaApi')
            ->willReturn([
                'success' => true,
                'message' => 'Rekap Umpan Balik 360 berhasil diproses untuk 1 pegawai.',
                'updated_count' => 1,
                'not_found_nips' => [],
            ]);

        $this->app->instance(UmpanBalik360Service::class, $mockService);

        $response = $this->withHeaders([
            'Origin' => 'https://simantap.dpd.go.id',
            'X-API-TOKEN' => $encryptedToken,
            'Accept' => 'application/json',
        ])->postJson('/api/umpan-balik-360/sync');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Rekap Umpan Balik 360 berhasil diproses untuk 1 pegawai.',
                'updated_count' => 1,
                'not_found_nips' => [],
            ]);
    }

    public function test_sync_endpoint_handles_failure(): void
    {
        $token = 'test-token';
        config()->set('app.api_tokens', [$token]);
        config()->set('cors.allowed_origins', ['https://simantap.dpd.go.id']);
        $encryptedToken = TokenEncryptionService::encryptTokenForHeader($token, ['salt' => $token]);

        $mockService = $this->createMock(UmpanBalik360Service::class);
        $mockService->method('syncFromNusaApi')
            ->willReturn([
                'success' => false,
                'message' => 'Gagal mengambil data dari NUSA API: HTTP 500',
            ]);

        $this->app->instance(UmpanBalik360Service::class, $mockService);

        $response = $this->withHeaders([
            'Origin' => 'https://simantap.dpd.go.id',
            'X-API-TOKEN' => $encryptedToken,
            'Accept' => 'application/json',
        ])->postJson('/api/umpan-balik-360/sync');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Gagal mengambil data dari NUSA API: HTTP 500',
            ]);
    }
}
