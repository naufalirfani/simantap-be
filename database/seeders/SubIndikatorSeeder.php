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
        $penilaianSpesifik = Indikator::where('indikator', 'Penilaian Utama')->first();
        $penilaianGenerik = Indikator::where('indikator', 'Penilaian Penguat')->first();
        $penilaianPotensiTalenta = Indikator::where('indikator', 'Penilaian Potensi Talenta')->first();
        $rekamJejakJabatan = Indikator::where('indikator', 'Rekam Jejak Jabatan')->first();
        $kualifikasi = Indikator::where('indikator', 'Kualifikasi')->first();
        $penilaianKompetensiTalenta = Indikator::where('indikator', 'Penilaian Kompetensi Manajerial dan Sosial Kultural')->first();

        $subIndikators = [
            // Penilaian Utama
            [
                'subindikator' => 'Penilaian Kerja (SKP)',
                'bobot' => 40.00,
                'isactive' => true,
                'indikator_id' => $penilaianSpesifik->id,
            ],
            // Penilaian Penguat
            [
                'subindikator' => 'Tugas Tambahan',
                'bobot' => 5.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            [
                'subindikator' => 'Inovasi',
                'bobot' => 10.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            [
                'subindikator' => 'Kehadiran',
                'bobot' => 15.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            [
                'subindikator' => 'Penugasan dalam Tim Kerja',
                'bobot' => 15.00,
                'isactive' => true,
                'indikator_id' => $penilaianGenerik->id,
            ],
            [
                'subindikator' => 'Penghargaan atas Capaian Kinerja',
                'bobot' => 15.00,
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
                'subindikator' => 'Diklat Kepemimpinan/Keahlian/Penjenjangan',
                'bobot' => 5.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Integritas/Moralitas',
                'bobot' => 15.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Pengembangan Kompetensi',
                'bobot' => 5.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Lama Jabatan',
                'bobot' => 4.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Keragaman Riwayat Jabatan',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            [
                'subindikator' => 'Penugasan Dalam Jabatan Nondefinitif',
                'bobot' => 3.00,
                'isactive' => true,
                'indikator_id' => $rekamJejakJabatan->id,
            ],
            // Kualifikasi
            [
                'subindikator' => 'Tingkat Pendidikan Formal',
                'bobot' => 10.00,
                'isactive' => true,
                'indikator_id' => $kualifikasi->id,
            ],
            [
                'subindikator' => 'Kesesuaian Pendidikan dengan Jabatan Target',
                'bobot' => 10.00,
                'isactive' => true,
                'indikator_id' => $kualifikasi->id,
            ],
            // Penilaian Kompetensi Talenta
            [
                'subindikator' => 'Perekat Bangsa',
                'bobot' => 2.00,
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
                'bobot' => 2.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Integritas',
                'bobot' => 2.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Pengambilan Keputusan',
                'bobot' => 2.00,
                'isactive' => true,
                'indikator_id' => $penilaianKompetensiTalenta->id,
            ],
            [
                'subindikator' => 'Pengembangan Diri dan Orang Lain',
                'bobot' => 2.00,
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
