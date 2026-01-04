<?php

namespace App\Console\Commands;

use App\Models\Pegawai;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPegawai extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:pegawai';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync pegawai data from external API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Set time limit to 10 minutes for long-running sync
        set_time_limit(600);
        
        $this->info('Starting pegawai synchronization...');
        Log::info('Pegawai sync started');

        try {
            $apiUrl = env('PEGAWAI_API_URL', 'https://cmb.tail91813a.ts.net/api/pegawai');
            $apiToken = env('PEGAWAI_API_TOKEN');

            if (!$apiToken) {
                $this->error('PEGAWAI_API_TOKEN not configured in .env');
                Log::error('PEGAWAI_API_TOKEN not configured');
                return Command::FAILURE;
            }

            $this->info("API URL: {$apiUrl}");
            Log::info("Fetching from API: {$apiUrl}");

            // Fetch all pages
            $allData = [];
            $currentPage = 1;
            $lastPage = 1;

            do {
                $this->info("Fetching page {$currentPage}...");
                Log::info("Fetching page {$currentPage}");
                $response = Http::withHeaders([
                    'X-API-TOKEN' => $apiToken,
                    'Accept' => 'application/json',
                ])
                ->timeout(180)
                ->get($apiUrl, [
                    'include_avatar' => 'true',
                    'per_page' => 100,
                    'page' => $currentPage,
                ]);
                /** @var \Illuminate\Http\Client\Response $response */
                if (!$response->successful()) {
                    $this->error("Failed to fetch page {$currentPage}: HTTP " . $response->status());
                    Log::error('Pegawai sync failed', [
                        'page' => $currentPage,
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 500),
                    ]);
                    return Command::FAILURE;
                }

                $data = $response->json();
                
                if (!isset($data['data']) || !is_array($data['data'])) {
                    $this->error('Invalid response format');
                    Log::error('Invalid response format', [
                        'page' => $currentPage,
                        'response_keys' => array_keys($data ?? []),
                    ]);
                    return Command::FAILURE;
                }

                $pageCount = count($data['data']);
                $this->info("  Retrieved {$pageCount} records");
                Log::info("Page {$currentPage} retrieved {$pageCount} records");

                $allData = array_merge($allData, $data['data']);
                
                $lastPage = $data['meta']['last_page'] ?? 1;
                $currentPage++;

            } while ($currentPage <= $lastPage);

            $totalRecords = count($allData);
            $this->info("Fetched {$totalRecords} records total");
            Log::info("Total records fetched: {$totalRecords}");

            // Sync to database
            $this->syncToDatabase($allData);

            $this->info('Synchronization completed successfully!');
            Log::info('Pegawai sync completed successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error during synchronization: ' . $e->getMessage());
            Log::error('Pegawai Sync Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Sync data to database
     */
    private function syncToDatabase(array $data): void
    {
        $this->info('Starting database sync...');
        Log::info('Starting database sync', ['total_records' => count($data)]);

        DB::transaction(function () use ($data) {
            $synced = 0;
            $updated = 0;
            $inserted = 0;
            $errors = 0;

            foreach ($data as $index => $item) {
                try {
                    if (!isset($item['id'])) {
                        $this->warn("Record #{$index} missing 'id' field, skipping");
                        Log::warning("Record missing id", ['index' => $index, 'nip' => $item['nip'] ?? 'unknown']);
                        $errors++;
                        continue;
                    }

                    $pegawaiId = $item['id'];
                    $exists = Pegawai::where('pegawai_id', $pegawaiId)->exists();

                    $pegawaiData = [
                        'pegawai_id' => $pegawaiId,
                        'nip' => $item['nip'],
                        'name' => $item['name'],
                        'email' => $item['email'] ?? null,
                        'unit_organisasi_name' => $item['unit_organisasi_name'] ?? null,
                        'jabatan_name' => $item['jabatan_name'] ?? null,
                        'jenis_jabatan' => $item['jenis_jabatan'] ?? null,
                        'golongan' => $item['golongan'] ?? null,
                        'json' => $item['json'] ?? [],
                        'avatar' => $item['avatar'] ?? null,
                    ];

                    if ($exists) {
                        Pegawai::where('pegawai_id', $pegawaiId)->update($pegawaiData);
                        $updated++;
                        
                        if (($updated % 50) == 0) {
                            $this->info("  Updated {$updated} records so far...");
                        }
                    } else {
                        Pegawai::create($pegawaiData);
                        $inserted++;
                        
                        if (($inserted % 50) == 0) {
                            $this->info("  Inserted {$inserted} records so far...");
                        }
                    }

                    $synced++;

                } catch (\Exception $e) {
                    $errors++;
                    $this->error("Error syncing record #{$index}: " . $e->getMessage());
                    Log::error("Error syncing pegawai record", [
                        'index' => $index,
                        'pegawai_id' => $item['id'] ?? 'unknown',
                        'nip' => $item['nip'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->info("Synced: {$synced} records (Inserted: {$inserted}, Updated: {$updated}, Errors: {$errors})");
            Log::info('Database sync completed', [
                'synced' => $synced,
                'inserted' => $inserted,
                'updated' => $updated,
                'errors' => $errors,
            ]);
        });
    }
}
