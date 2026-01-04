<?php

namespace Database\Seeders;

use App\Models\Indikator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IndikatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $indikators = [
            [
                'indikator' => 'Penilaian Generik',
                'bobot' => 0.00,
                'penilaian' => 'Kinerja',
            ],
            [
                'indikator' => 'Penilaian Spesifik',
                'bobot' => 0.00,
                'penilaian' => 'Kinerja',
            ],
            [
                'indikator' => 'Penilaian Kompetensi Talenta',
                'bobot' => 0.00,
                'penilaian' => 'Potensial',
            ],
            [
                'indikator' => 'Rekam Jejak Jabatan',
                'bobot' => 0.00,
                'penilaian' => 'Potensial',
            ],
            [
                'indikator' => 'Pertimbangan lainnya',
                'bobot' => 0.00,
                'penilaian' => 'Potensial',
            ],
            [
                'indikator' => 'Penilaian Potensi Talenta',
                'bobot' => 0.00,
                'penilaian' => 'Potensial',
            ],
        ];

        foreach ($indikators as $indikator) {
            $exists = DB::table('indikators')
                ->where('indikator', $indikator['indikator'])
                ->exists();

            if ($exists) {
                DB::table('indikators')
                    ->where('indikator', $indikator['indikator'])
                    ->update($indikator);
            } else {
                $toInsert = array_merge(['id' => (string) Str::uuid()], $indikator);
                DB::table('indikators')->insert($toInsert);
            }
        }
    }
}
