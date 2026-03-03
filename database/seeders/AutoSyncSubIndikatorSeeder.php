<?php

namespace Database\Seeders;

use App\Models\SubIndikator;
use Illuminate\Database\Seeder;

class AutoSyncSubIndikatorSeeder extends Seeder
{
    private const AUTO_SYNC_SUBINDIKATORS = [
        'Penilaian Kerja (SKP)',
        'Tingkat Pendidikan Formal',
        'Integritas/Moralitas'
    ];

    public function run(): void
    {
        $updated = SubIndikator::whereIn('subindikator', self::AUTO_SYNC_SUBINDIKATORS)
            ->update(['auto_sync' => true]);

        $this->command->info("AutoSyncSubIndikatorSeeder: {$updated} subindikator(s) marked as auto_sync.");

        // Warn about any names not found in the database
        $found = SubIndikator::whereIn('subindikator', self::AUTO_SYNC_SUBINDIKATORS)
            ->pluck('subindikator')
            ->all();

        $missing = array_diff(self::AUTO_SYNC_SUBINDIKATORS, $found);
        foreach ($missing as $name) {
            $this->command->warn("AutoSyncSubIndikatorSeeder: subindikator '{$name}' not found in database.");
        }
    }
}
