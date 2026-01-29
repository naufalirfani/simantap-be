<?php

namespace Database\Seeders;

use App\Models\JenisJabatan;
use App\Models\StandarKompetensiMsk;
use App\Models\SubIndikator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StandarKompetensiMskSeeder extends Seeder
{
    use WithoutModelEvents;

    private function normalize($s)
    {
        $s = trim(mb_strtolower((string) $s));
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($trans !== false) $s = $trans;
        $s = preg_replace('/[^a-z0-9]+/i', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    public function run(): void
    {
        $map = [
            'Jabatan Pimpinan Tinggi Utama' => [
                'Integritas' => 5,
                'Kerja Sama' => 5,
                'Komunikasi' => 5,
                'Orientasi Pada Hasil' => 5,
                'Pelayanan Publik' => 5,
                'Pengembangan Diri dan Orang Lain' => 5,
                'Mengelola Perubahan' => 5,
                'Pengambilan Keputusan' => 5,
                'Perekat Bangsa' => 5,
            ],
            'Jabatan Pimpinan Tinggi Madya' => [
                'Integritas' => 5,
                'Kerja Sama' => 5,
                'Komunikasi' => 5,
                'Orientasi Pada Hasil' => 5,
                'Pelayanan Publik' => 5,
                'Pengembangan Diri dan Orang Lain' => 5,
                'Mengelola Perubahan' => 5,
                'Pengambilan Keputusan' => 5,
                'Perekat Bangsa' => 5,
            ],
            'Jabatan Pimpinan Tinggi Pratama' => [
                'Integritas' => 4,
                'Kerja Sama' => 4,
                'Komunikasi' => 4,
                'Orientasi Pada Hasil' => 4,
                'Pelayanan Publik' => 4,
                'Pengembangan Diri dan Orang Lain' => 4,
                'Mengelola Perubahan' => 4,
                'Pengambilan Keputusan' => 4,
                'Perekat Bangsa' => 4,
            ],
            'Jabatan Administrator' => [
                'Integritas' => 3,
                'Kerja Sama' => 3,
                'Komunikasi' => 3,
                'Orientasi Pada Hasil' => 3,
                'Pelayanan Publik' => 3,
                'Pengembangan Diri dan Orang Lain' => 3,
                'Mengelola Perubahan' => 3,
                'Pengambilan Keputusan' => 3,
                'Perekat Bangsa' => 3,
            ],
            'Jabatan Pengawas' => [
                'Integritas' => 2,
                'Kerja Sama' => 2,
                'Komunikasi' => 2,
                'Orientasi Pada Hasil' => 2,
                'Pelayanan Publik' => 2,
                'Pengembangan Diri dan Orang Lain' => 2,
                'Mengelola Perubahan' => 2,
                'Pengambilan Keputusan' => 2,
                'Perekat Bangsa' => 2,
            ],
            'Jabatan Fungsional Ahli Utama' => [
                'Integritas' => 5,
                'Kerja Sama' => 4,
                'Komunikasi' => 4,
                'Orientasi Pada Hasil' => 4,
                'Pelayanan Publik' => 4,
                'Pengembangan Diri dan Orang Lain' => 4,
                'Mengelola Perubahan' => 4,
                'Pengambilan Keputusan' => 4,
                'Perekat Bangsa' => 5,
            ],
            'Jabatan Fungsional Ahli Madya' => [
                'Integritas' => 4,
                'Kerja Sama' => 4,
                'Komunikasi' => 4,
                'Orientasi Pada Hasil' => 4,
                'Pelayanan Publik' => 4,
                'Pengembangan Diri dan Orang Lain' => 4,
                'Mengelola Perubahan' => 4,
                'Pengambilan Keputusan' => 4,
                'Perekat Bangsa' => 4,
            ],
            'Jabatan Fungsional Ahli Muda' => [
                'Integritas' => 3,
                'Kerja Sama' => 3,
                'Komunikasi' => 3,
                'Orientasi Pada Hasil' => 3,
                'Pelayanan Publik' => 3,
                'Pengembangan Diri dan Orang Lain' => 3,
                'Mengelola Perubahan' => 3,
                'Pengambilan Keputusan' => 3,
                'Perekat Bangsa' => 3,
            ],
            'Jabatan Fungsional Ahli Pertama' => [
                'Integritas' => 2,
                'Kerja Sama' => 2,
                'Komunikasi' => 2,
                'Orientasi Pada Hasil' => 2,
                'Pelayanan Publik' => 2,
                'Pengembangan Diri dan Orang Lain' => 2,
                'Mengelola Perubahan' => 2,
                'Pengambilan Keputusan' => 2,
                'Perekat Bangsa' => 2,
            ],
            'Jabatan Fungsional Penyelia' => [
                'Integritas' => 3,
                'Kerja Sama' => 3,
                'Komunikasi' => 3,
                'Orientasi Pada Hasil' => 3,
                'Pelayanan Publik' => 3,
                'Pengembangan Diri dan Orang Lain' => 3,
                'Mengelola Perubahan' => 3,
                'Pengambilan Keputusan' => 3,
                'Perekat Bangsa' => 3,
            ],
            'Jabatan Fungsional Mahir' => [
                'Integritas' => 2,
                'Kerja Sama' => 2,
                'Komunikasi' => 2,
                'Orientasi Pada Hasil' => 2,
                'Pelayanan Publik' => 2,
                'Pengembangan Diri dan Orang Lain' => 2,
                'Mengelola Perubahan' => 2,
                'Pengambilan Keputusan' => 2,
                'Perekat Bangsa' => 2,
            ],
            'Jabatan Fungsional Terampil' => [
                'Integritas' => 2,
                'Kerja Sama' => 2,
                'Komunikasi' => 1,
                'Orientasi Pada Hasil' => 1,
                'Pelayanan Publik' => 1,
                'Pengembangan Diri dan Orang Lain' => 1,
                'Mengelola Perubahan' => 1,
                'Pengambilan Keputusan' => 1,
                'Perekat Bangsa' => 2,
            ],
            'Jabatan Fungsional Pemula' => [
                'Integritas' => 1,
                'Kerja Sama' => 1,
                'Komunikasi' => 1,
                'Orientasi Pada Hasil' => 1,
                'Pelayanan Publik' => 1,
                'Pengembangan Diri dan Orang Lain' => 1,
                'Mengelola Perubahan' => 1,
                'Pengambilan Keputusan' => 1,
                'Perekat Bangsa' => 1,
            ],
            'Jabatan Pelaksana' => [
                'Integritas' => 1,
                'Kerja Sama' => 1,
                'Komunikasi' => 1,
                'Orientasi Pada Hasil' => 1,
                'Pelayanan Publik' => 1,
                'Pengembangan Diri dan Orang Lain' => 1,
                'Mengelola Perubahan' => 1,
                'Pengambilan Keputusan' => 1,
                'Perekat Bangsa' => 1,
            ],
        ];

        // build lookup for subindikator names
        $subs = SubIndikator::all();
        $subsMap = [];
        foreach ($subs as $s) {
            $subsMap[$this->normalize($s->subindikator ?? '')] = $s->id;
        }

        foreach ($map as $jenisName => $items) {
            $jenis = JenisJabatan::where('name', $jenisName)->first();
            if (!$jenis) {
                $this->command->warn("Jenis jabatan not found: {$jenisName}");
                continue;
            }

            foreach ($items as $subName => $val) {
                $key = $this->normalize($subName);
                if (!isset($subsMap[$key])) {
                    $this->command->warn("Subindikator not found: {$subName}");
                    continue;
                }

                StandarKompetensiMsk::updateOrCreate([
                    'jenis_jabatan_id' => $jenis->id,
                    'subindikator_id' => $subsMap[$key],
                ], ['standar' => (int) $val]);
            }
        }
    }
}
