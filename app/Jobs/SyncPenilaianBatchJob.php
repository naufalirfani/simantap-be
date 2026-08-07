<?php

namespace App\Jobs;

use App\Services\PenilaianSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes a batch (chunk) of NIP values through PenilaianSyncService.
 * Intended to be dispatched by DispatchSyncPenilaianJob, with at most 100 NIPs per job.
 */
class SyncPenilaianBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum execution time in seconds (10 minutes per batch). */
    public int $timeout = 600;

    /** Number of times the job may be attempted on failure. */
    public int $tries = 2;

    /**
     * @param  string[] $nips  NIPs to process in this batch (max 100).
     */
    public function __construct(private readonly array $nips) {}

    public function handle(PenilaianSyncService $syncService): void
    {
        Log::info('SyncPenilaianBatchJob: starting batch', [
            'count' => count($this->nips),
            'nips'  => $this->nips,
        ]);

        try {
            $result = $syncService->syncPenilaian($this->nips);

            Log::info('SyncPenilaianBatchJob: batch complete', [
                'updated' => $result['updated'],
                'errors'  => count($result['errors']),
            ]);

            if (!empty($result['errors'])) {
                Log::warning('SyncPenilaianBatchJob: some records had errors', ['errors' => $result['errors']]);
            }
        } catch (\Throwable $e) {
            Log::error('SyncPenilaianBatchJob failed: ' . $e->getMessage(), [
                'nips'      => $this->nips,
                'exception' => $e,
            ]);
            $this->markSessionFailed($e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->markSessionFailed($exception->getMessage());
    }

    private function markSessionFailed(string $errorMessage): void
    {
        try {
            $latest = DB::table('penilaian_sync_sessions')->latest('id')->first();
            if ($latest && $latest->status !== 'failed') {
                DB::table('penilaian_sync_sessions')->where('id', $latest->id)->update([
                    'status'        => 'failed',
                    'error_message' => "Batch error: " . $errorMessage,
                    'updated_at'    => now(),
                ]);
            }
        } catch (\Throwable $ex) {
            // Ignore DB errors in fallback
        }
    }
}
