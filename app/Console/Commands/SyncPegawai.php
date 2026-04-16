<?php

namespace App\Console\Commands;

use App\Http\Controllers\StatistikController;
use App\Models\Pegawai;
use App\Models\PetaJabatan;
use App\Models\JenisJabatan;
use App\Models\Penilaian;
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
            $apiBaseUrl = rtrim(env('CMB_API_URL', 'https://cmb.tail91813a.ts.net/api'), '/');
            $apiToken = env('CMB_API_TOKEN');

            if (!$apiToken) {
                $this->error('CMB_API_TOKEN not configured in .env');
                Log::error('CMB_API_TOKEN not configured');
                return Command::FAILURE;
            }

            $apiUrl = "{$apiBaseUrl}/pegawai";

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
                        'include_riwayat_pendidikan' => 'true',
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
            (new StatistikController())->sync();
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

        // Pre-fetch jenis_jabatan mapping for performance
        $jenisJabatanMap = JenisJabatan::pluck('id', 'name')->toArray();

        DB::transaction(function () use ($data, $jenisJabatanMap) {
            $synced = 0;
            $updated = 0;
            $inserted = 0;
            $deleted = 0;
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
                    $pegawai = Pegawai::where('pegawai_id', $pegawaiId)->first();
                    $exists = (bool) $pegawai;

                    // If remote status is INACTIVE, remove local penilaian and pegawai record
                    $status = strtoupper($item['status'] ?? '');
                    if ($status === 'INACTIVE') {
                        if ($pegawai) {
                            try {
                                Penilaian::where('pegawai_id', $pegawai->id)->delete();
                                $pegawai->delete();
                                $deleted++;
                                if (($deleted % 50) == 0) {
                                    $this->info("  Deleted {$deleted} inactive records so far...");
                                }
                            } catch (\Exception $e) {
                                Log::error('Error deleting inactive pegawai', ['pegawai_id' => $pegawaiId, 'error' => $e->getMessage()]);
                                $errors++;
                            }
                        }

                        // Skip further processing for this record
                        $synced++;
                        continue;
                    }

                    // Try to resolve peta_jabatan_id by matching jabatan and unit
                    $petaJabatanId = null;
                    try {
                        $jabatanName = $item['jabatan_name'] ?? null;
                        $unitName = $item['unit_organisasi_name'] ?? null;

                        if ($jabatanName) {
                            // perform case-insensitive matching using lower() to support multiple DBs
                            $q = PetaJabatan::query()->whereRaw('lower(nama_jabatan) = ?', [strtolower($jabatanName)]);
                            if ($unitName) {
                                $q->whereRaw('lower(unit_kerja) = ?', [strtolower($unitName)]);
                            }

                            $found = $q->first();
                            if ($found) {
                                $petaJabatanId = $found->id;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error resolving peta_jabatan_id: ' . $e->getMessage(), ['nip' => $item['nip'] ?? null]);
                    }

                    // Resolve jenis_jabatan_id based on eselonLevel and jabatan_name patterns
                    $jenisJabatanId = $this->resolveJenisJabatanId($item, $jenisJabatanMap);

                    $pegawaiData = [
                        'pegawai_id' => $pegawaiId,
                        'nip' => $item['nip'],
                        'name' => $item['name'],
                        'email' => $item['json'] && isset($item['json']['emailGov']) ? $item['json']['emailGov'] : ($item['email'] ?? null),
                        'unit_organisasi_name' => $item['unit_organisasi_name'] ?? null,
                        'jabatan_name' => $item['jabatan_name'] ?? null,
                        'jenis_jabatan' => $item['jenis_jabatan'] ?? null,
                        'jenis_jabatan_id' => $jenisJabatanId,
                        'peta_jabatan_id' => $petaJabatanId,
                        'golongan' => $item['golongan'] ?? null,
                        'json' => $item['json'] ?? [],
                        'avatar' => $item['avatar'] ?? null,
                        'riwayat_pendidikan' => $item['riwayat_pendidikan'] ?? null,
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

            $this->info("Synced: {$synced} records (Inserted: {$inserted}, Updated: {$updated}, Deleted: {$deleted}, Errors: {$errors})");
            Log::info('Database sync completed', [
                'synced' => $synced,
                'inserted' => $inserted,
                'updated' => $updated,
                'deleted' => $deleted,
                'errors' => $errors,
            ]);
        });
    }

    /**
     * Resolve jenis_jabatan_id based on eselonLevel and jabatan_name patterns
     */
    private function resolveJenisJabatanId(array $item, array $jenisJabatanMap): ?string
    {
        $json = $item['json'] ?? [];
        $eselonLevel = $json['eselonLevel'] ?? null;
        $jabatanName = $item['jabatan_name'] ?? '';
        $jenisJabatan = strtolower($item['jenis_jabatan'] ?? '');

        // Map based on eselonLevel
        if ($eselonLevel === '0') {
            return $jenisJabatanMap['Jabatan Pimpinan Tinggi Utama'] ?? null;
        }
        if ($eselonLevel === '1') {
            return $jenisJabatanMap['Jabatan Pimpinan Tinggi Madya'] ?? null;
        }
        if ($eselonLevel === '2') {
            return $jenisJabatanMap['Jabatan Pimpinan Tinggi Pratama'] ?? null;
        }
        if ($eselonLevel === '3') {
            return $jenisJabatanMap['Jabatan Administrator'] ?? null;
        }
        if ($eselonLevel === '4') {
            return $jenisJabatanMap['Jabatan Pengawas'] ?? null;
        }

        // Map based on jabatan_name patterns (check in order of specificity)
        if (stripos($jabatanName, 'Ahli Utama') !== false) {
            return $jenisJabatanMap['Jabatan Fungsional Ahli Utama'] ?? null;
        }
        if (stripos($jabatanName, 'Ahli Madya') !== false) {
            return $jenisJabatanMap['Jabatan Fungsional Ahli Madya'] ?? null;
        }
        if (stripos($jabatanName, 'Ahli Muda') !== false) {
            return $jenisJabatanMap['Jabatan Fungsional Ahli Muda'] ?? null;
        }
        if (stripos($jabatanName, 'Ahli Pertama') !== false) {
            return $jenisJabatanMap['Jabatan Fungsional Ahli Pertama'] ?? null;
        }
        if (stripos($jabatanName, 'Penyelia') !== false) {
            return $jenisJabatanMap['Jabatan Fungsional Penyelia'] ?? null;
        }
        if (stripos($jabatanName, 'Mahir') !== false) {
            return $jenisJabatanMap['Jabatan Fungsional Mahir'] ?? null;
        }
        if (stripos($jabatanName, 'Terampil') !== false) {
            return $jenisJabatanMap['Jabatan Fungsional Terampil'] ?? null;
        }

        // Map based on jenis_jabatan field
        if ($jenisJabatan === 'pelaksana') {
            return $jenisJabatanMap['Jabatan Pelaksana'] ?? null;
        }

        return null;
    }
}
