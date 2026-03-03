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
                'indikator' => 'Penilaian Penguat',
                'bobot' => 40.00,
                'penilaian' => 'Kinerja',
            ],
            [
                'indikator' => 'Penilaian Utama',
                'bobot' => 60.00,
                'penilaian' => 'Kinerja',
            ],
            [
                'indikator' => 'Penilaian Kompetensi Manajerial dan Sosial Kultural',
                'bobot' => 20.00,
                'penilaian' => 'Potensial',
            ],
            [
                'indikator' => 'Rekam Jejak Jabatan',
                'bobot' => 35.00,
                'penilaian' => 'Potensial',
            ],
            [
                'indikator' => 'Kualifikasi',
                'bobot' => 20.00,
                'penilaian' => 'Potensial',
            ],
            [
                'indikator' => 'Penilaian Potensi Talenta',
                'bobot' => 25.00,
                'penilaian' => 'Potensial',
            ],
            [
                'indikator' => 'Umum',
                'penilaian' => 'Tambahan',
            ],
            [
                'indikator' => 'Kompetensi Teknis',
                'penilaian' => 'Tambahan',
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
