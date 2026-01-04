<?php

namespace App\Console\Commands;

use App\Models\Statistik;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateStatistik extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:statistik';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update statistik table with pegawai statistics';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Refreshing statistik materialized view...');
        Log::info('Statistik refresh started');

        try {
            $startTime = microtime(true);
            
            // Refresh materialized view
            DB::statement('REFRESH MATERIALIZED VIEW statistik');
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $count = DB::table('statistik')->count();

            $message = "Statistik refreshed successfully! ({$count} statistics, {$duration}ms)";
            $this->info($message);
            Log::info($message);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to refresh statistik: ' . $e->getMessage());
            Log::error('Statistik refresh failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
