<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPetaJabatan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:peta-jabatan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync peta jabatan data from external API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting peta jabatan synchronization...');

        try {
            // Step 1: Login to get access token
            $loginResponse = $this->login();

            if (!$loginResponse) {
                $this->error('Failed to login to external API');
                return Command::FAILURE;
            }

            $accessToken = $loginResponse['access_token'];
            $masked = substr($accessToken, 0, 8) . '...' . substr($accessToken, -8);
            $this->info('Successfully obtained access token: ' . $masked);

            // Step 2: Fetch peta jabatan data
            $petaJabatanData = $this->fetchPetaJabatan($accessToken);

            if (!$petaJabatanData) {
                $this->error('Failed to fetch peta jabatan data');
                return Command::FAILURE;
            }

            $this->info('Fetched ' . count($petaJabatanData) . ' records');

            // Step 3: Sync data to database
            $this->syncToDatabase($petaJabatanData);

            $this->info('Synchronization completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error during synchronization: ' . $e->getMessage());
            Log::error('Peta Jabatan Sync Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Login to external API
     */
    private function login(): ?array
    {
        $apiUrl = config('services.anjab.url', env('ANJAB_API_URL'));
        $email = config('services.anjab.email', env('ANJAB_API_EMAIL'));
        $password = config('services.anjab.password', env('ANJAB_API_PASSWORD'));

        if (!$email || !$password) {
            $this->error('API credentials not configured. Please set ANJAB_API_EMAIL and ANJAB_API_PASSWORD in .env');
            return null;
        }

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::post("{$apiUrl}/api/auth/login", [
                'email' => $email,
                'password' => $password,
            ]);

            if ($response->successful() && $response->json('ok')) {
                return $response->json();
            }

            $this->error('Login failed: ' . $response->body());
            Log::error('Peta Jabatan login failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ]);
            return null;

        } catch (\Exception $e) {
            $this->error('Login request failed: ' . $e->getMessage());
            Log::error('Peta Jabatan login exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Fetch peta jabatan data from external API
     */
    private function fetchPetaJabatan(string $accessToken): ?array
    {
        $apiUrl = config('services.anjab.url', env('ANJAB_API_URL'));

        try {
            $this->info('Using Authorization: Bearer ' . substr($accessToken,0,6) . '...' );
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->timeout(15)
                ->get("{$apiUrl}/api/peta-jabatan");

            if ($response->successful()) {
                $json = $response->json();
                if (is_array($json)) {
                    return $json;
                }

                $body = $response->body();
                $this->error('Fetch succeeded but response is not an array');
                Log::warning('Peta Jabatan fetch invalid JSON', [
                    'body' => $body,
                    'headers' => $response->headers(),
                ]);
                $this->line('Response body (logged, truncated): ' . substr($body, 0, 200) . '...');
                return null;
            }

            $this->error('Fetch failed: HTTP ' . $response->status());
            $this->line('Response body: ' . substr($response->body(), 0, 2000));
            Log::error('Peta Jabatan fetch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ]);
            return null;

        } catch (\Exception $e) {
            $this->error('Fetch request failed: ' . $e->getMessage());
            Log::error('Peta Jabatan fetch exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Sync data to database
     */
    private function syncToDatabase(array $data): void
    {
        DB::transaction(function () use ($data) {
            $synced = 0;
            $updated = 0;
            $inserted = 0;

            $position = 0;
            foreach ($data as $item) {
                $position++;
                $exists = DB::table('peta_jabatan')
                    ->where('id', $item['id'])
                    ->exists();

                $dataToSync = [
                    'id' => $item['id'],
                    'parent_id' => $item['parent_id'],
                    'nama_jabatan' => $item['nama_jabatan'],
                    'slug' => $item['slug'] ?? null,
                    'unit_kerja' => $item['unit_kerja'] ?? null,
                    'level' => $item['level'] ?? 0,
                    // Set order_index according to response order (1-based)
                    'order_index' => $item['order_index'] ?? $position,
                    'bezetting' => $item['bezetting'] ?? 0,
                    'kebutuhan_pegawai' => $item['kebutuhan_pegawai'] ?? 0,
                    'is_pusat' => $item['is_pusat'] ?? false,
                    'jenis_jabatan' => $item['jenis_jabatan'] ?? null,
                    'jabatan_id' => $item['jabatan_id'] ?? null,
                    'kelas_jabatan' => $item['kelas_jabatan'] ?? null,
                    'nama_pejabat' => json_encode($item['nama_pejabat'] ?? []),
                    'updated_at' => now(),
                ];

                if ($exists) {
                    DB::table('peta_jabatan')
                        ->where('id', $item['id'])
                        ->update($dataToSync);
                    $updated++;
                } else {
                    $dataToSync['created_at'] = now();
                    DB::table('peta_jabatan')->insert($dataToSync);
                    $inserted++;
                }

                $synced++;
            }

            $this->info("Synced: {$synced} records (Inserted: {$inserted}, Updated: {$updated})");
        });
    }
}
