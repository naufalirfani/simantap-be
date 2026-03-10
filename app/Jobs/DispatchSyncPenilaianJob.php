<?php

namespace App\Jobs;

use App\Models\Pegawai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrator job: fetches all relevant pegawai NIPs, chunks them into
 * batches of up to BATCH_SIZE, and dispatches a SyncPenilaianBatchJob
 * for each chunk so processing runs entirely in the background.
 */
class DispatchSyncPenilaianJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum NIPs processed per SyncPenilaianBatchJob. */
    private const BATCH_SIZE = 100;

    public int $timeout = 120;
    public int $tries   = 1;

    /**
     * @param  string[]|null $filterNips  When provided, restrict sync to these NIPs only.
     */
    public function __construct(private readonly ?array $filterNips = null) {}

    public function handle(): void
    {
        // Resolve the full list of NIPs to process
        $query = Pegawai::query()->select('nip');

        if ($this->filterNips !== null) {
            $query->whereIn('nip', $this->filterNips);
        }

        $nips = $query->pluck('nip')->map(fn ($n) => (string) $n)->all();

        if (empty($nips)) {
            Log::info('DispatchSyncPenilaianJob: no pegawai found, nothing to do.');
            return;
        }

        $chunks = array_chunk($nips, self::BATCH_SIZE);

        Log::info('DispatchSyncPenilaianJob: dispatching batch jobs', [
            'total_pegawai'  => count($nips),
            'total_batches'  => count($chunks),
            'batch_size'     => self::BATCH_SIZE,
        ]);

        // Store session totals so syncStatus can report accurate current-run progress
        $latest = DB::table('penilaian_sync_sessions')->latest('id')->first();
        if ($latest) {
            DB::table('penilaian_sync_sessions')->where('id', $latest->id)->update([
                'total_nips'    => count($nips),
                'total_batches' => count($chunks),
                'updated_at'    => now(),
            ]);
        }

        foreach ($chunks as $index => $chunk) {
            SyncPenilaianBatchJob::dispatch($chunk);

            Log::info("DispatchSyncPenilaianJob: dispatched batch #" . ($index + 1), [
                'count' => count($chunk),
            ]);
        }
    }
}
