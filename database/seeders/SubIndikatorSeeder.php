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
        $penilaianKompetensiTalenta = Indikator::where('indikator', 'Penilaian Kompetensi Manajerial dan Sosial Kultural')->first();

        $subIndikators = [
            // Penilaian Spesifik
            [
                'subindikator' => 'Perilaku Kerja',
                'bobot' => 10.00,
                'isactive' => true,
                'indikator_id' => $penilaianSpesifik->id,
            ],
            [
                'subindikator' => 'Capaian Kinerja',
                'bobot' => 30.00,
                'isactive' => true,
                'indikator_id' => $penilaianSpesifik->id,
            ],
            // Penilaian Generik
            [
                'subindikator' => 'Partisipasi dalam Organisasi',
                'bobot' => 15.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            [
                'subindikator' => 'Tugas Tambahan',
                'bobot' => 10.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            [
                'subindikator' => 'Inovasi',
                'bobot' => 15.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            [
                'subindikator' => 'Disiplin',
                'bobot' => 20.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            // Penilaian Potensi Talenta
            [
                'subindikator' => 'Kemampuan belajar cepat dan mengembangkan diri',
                'bobot' => 4.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kemampuan menyelesaikan permasalahan',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kesadaran diri',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kemampuan intelektual',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Motivasi dan komitmen',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kecerdasan emosional',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kemampuan berpikir kritis dan strategis',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            [
                'subindikator' => 'Kemampuan interpersonal',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianPotensiTalenta->id,
            ],
            // Rekam Jejak Jabatan
            [
                'subindikator' => 'Penghargaan',
                'bobot' => 4.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Diklat Kepemimpinan',
                'bobot' => 4.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Masa kerja',
                'bobot' => 4.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Kualifikasi',
                'bobot' => 4.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Integritas/Moralitas',
                'bobot' => 4.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Pengembangan Kompetensi',
                'bobot' => 4.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Pengalaman dalam Organisasi',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Pangkat/Golongan Ruang',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            // Pertimbangan lainnya
            [
                'subindikator' => 'Pengalaman Organisasi',
                'bobot' => 5.00,
                'isactive' => true,
                'indikator_id' => $pertimbanganLainnya->id,
            ],
            [
                'subindikator' => 'Pertimbangan Atasan',
                'bobot' => 5.00,
                'isactive' => true,
                'indikator_id' => $pertimbanganLainnya->id,
            ],
            [
                'subindikator' => 'Kesesuaian Pendidikan dengan Jabatan Target',
                'bobot' => 10.00,
                'isactive' => true,
                'indikator_id' => $pertimbanganLainnya->id,
            ],
            // Penilaian Kompetensi Talenta
            [
                'subindikator' => 'Perekat Bangsa',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Mengelola Perubahan',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Pelayanan Publik',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Orientasi Pada Hasil',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Integritas',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Pengambilan Keputusan',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Pengembangan Diri dan Orang Lain',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Kerja Sama',
                'bobot' => 2.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Komunikasi',
                'bobot' => 2.00,
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
