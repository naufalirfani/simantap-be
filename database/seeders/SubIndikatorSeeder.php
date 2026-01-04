<?php

namespace Database\Seeders;

use App\Models\Indikator;
use App\Models\SubIndikator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubIndikatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get Indikator IDs
        $penilaianSpesifik = Indikator::where('indikator', 'Penilaian Spesifik')->first();
        $penilaianGenerik = Indikator::where('indikator', 'Penilaian Generik')->first();
        $penilaianPotensiTalenta = Indikator::where('indikator', 'Penilaian Potensi Talenta')->first();
        $rekamJejakJabatan = Indikator::where('indikator', 'Rekam Jejak Jabatan')->first();
        $pertimbanganLainnya = Indikator::where('indikator', 'Pertimbangan lainnya')->first();
        $penilaianKompetensiTalenta = Indikator::where('indikator', 'Penilaian Kompetensi Talenta')->first();

        $subIndikators = [
            // Penilaian Spesifik
            [
                'subindikator' => 'Perilaku Kerja',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianSpesifik->id,
            ],
            [
                'subindikator' => 'Capaian Kinerja',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianSpesifik->id,
            ],
            // Penilaian Generik
            [
                'subindikator' => 'Partisipasi Dalam Organisasi',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            [
                'subindikator' => 'Tugas Tambahan',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            [
                'subindikator' => 'Inovasi',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            [
                'subindikator' => 'Disiplin',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            // Penilaian Potensi Talenta
            [
                'subindikator' => 'Kemampuan belajar cepat dan mengembangkan diri',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kemampuan menyelesaikan permasalahan',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kesadaran diri',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kemampuan intelektual',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Motivasi dan komitmen',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kecerdasan emosional',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kemampuan berpikir kritis dan strategis',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kemampuan interpersonal',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            // Rekam Jejak Jabatan
            [
                'subindikator' => 'Penghargaan',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Diklat Kepemimpinan',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Masa kerja',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Kualifikasi',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
                        [
                'subindikator' => 'Integritas/Moralitas',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Pengembangan Kompetensi',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Pengalaman dalam Organisasi',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Pangkat/Golongan Ruang',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            // Pertimbangan lainnya
            [
                'subindikator' => 'Pengalaman Organisasi',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $pertimbanganLainnya->id,
            ],
            [
                'subindikator' => 'Pertimbangan Atasan',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $pertimbanganLainnya->id,
            ],
            [
                'subindikator' => 'Kesesuaian Pendidikan dengan Jabatan Target',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $pertimbanganLainnya->id,
            ],
            // Penilaian Kompetensi Talenta
            [
                'subindikator' => 'Perekat Bangsa',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Mengelola Perubahan',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Pelayanan Publik',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Orientasi Pada Hasil',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Integritas',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Pengambilan Keputusan',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Pengembangan Diri dan Orang Lain',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Kerja Sama',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Komunikasi',
                'bobot' => 0.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
        ];

        foreach ($subIndikators as $subIndikator) {
            $exists = DB::table('subindikators')
                ->where('subindikator', $subIndikator['subindikator'])
                ->where('indikator_id', $subIndikator['indikator_id'])
                ->exists();

            if ($exists) {
                DB::table('subindikators')
                    ->where('subindikator', $subIndikator['subindikator'])
                    ->where('indikator_id', $subIndikator['indikator_id'])
                    ->update($subIndikator);
            } else {
                $toInsert = array_merge(['id' => (string) Str::uuid()], $subIndikator);
                DB::table('subindikators')->insert($toInsert);
            }
        }
    }
}
