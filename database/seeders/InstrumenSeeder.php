<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\SubIndikator;

class InstrumenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            // Tingkat Pendidikan Formal
            ['parent' => 'Tingkat Pendidikan Formal', 'instrumen' => 'a. Sarjana Strata 3 (S3)', 'skor' => 100.00],
            ['parent' => 'Tingkat Pendidikan Formal', 'instrumen' => 'b. Sarjana Strata (S2)', 'skor' => 90.00],
            ['parent' => 'Tingkat Pendidikan Formal', 'instrumen' => 'c. Sarjana Strata (S1) atau Diploma IV (D.IV)', 'skor' => 80.00],
            ['parent' => 'Tingkat Pendidikan Formal', 'instrumen' => 'd. Diploma III (D3)', 'skor' => 70.00],
            ['parent' => 'Tingkat Pendidikan Formal', 'instrumen' => 'e. Sekolah Lanjutan Tingkat Atas (SLTA)', 'skor' => 60.00],

            // Lama Jabatan
            ['parent' => 'Lama Jabatan', 'instrumen' => 'a. Memiliki masa kerja dalam jenjang jabatan 5 tahun keatas', 'skor' => 100.00],
            ['parent' => 'Lama Jabatan', 'instrumen' => 'b. Memiliki masa kerja dalam jenjang jabatan 3 s.d 4 tahun', 'skor' => 80.00],
            ['parent' => 'Lama Jabatan', 'instrumen' => 'e. Memiliki masa kerja dalam jenjang jabatan 0 s.d 2 tahun', 'skor' => 60.00],
            // Keragaman Riwayat Jabatan
            ['parent' => 'Keragaman Riwayat Jabatan', 'instrumen' => 'a. Memiliki pengalaman jabatan lintas instansi', 'skor' => 100.00],
            ['parent' => 'Keragaman Riwayat Jabatan', 'instrumen' => 'b. Memiliki pengalaman jabatan lintas unit kerja', 'skor' => 80.00],
            ['parent' => 'Keragaman Riwayat Jabatan', 'instrumen' => 'c. Memiliki pengalaman jabatan hanya dalam 1 unit kerja', 'skor' => 60.00],
            // Penugasan Dalam Jabatan Nondefinitif
            ['parent' => 'Penugasan Dalam Jabatan Nondefinitif', 'instrumen' => 'a. Memiliki pengalaman penugasan jabatan non-ASN sebagai Penjabat Kepala Daerah', 'skor' => 100.00],
            ['parent' => 'Penugasan Dalam Jabatan Nondefinitif', 'instrumen' => 'b. Memiliki pengalaman penugasan sebagai Pelaksana Tugas pada jenjang jabatan yang lebih tinggi', 'skor' => 80.00],
            ['parent' => 'Penugasan Dalam Jabatan Nondefinitif', 'instrumen' => 'c. Memiliki pengalaman penugasan sebagai Pelaksana Tugas pada jabatan yang setara', 'skor' => 60.00],
            ['parent' => 'Penugasan Dalam Jabatan Nondefinitif', 'instrumen' => 'd. Memiliki pengalaman penugasan sebagai Pelaksana Harian pada jenjang jabatan yang lebih tinggi', 'skor' => 40.00],
            ['parent' => 'Penugasan Dalam Jabatan Nondefinitif', 'instrumen' => 'e. Memiliki pengalaman penugasan sebagai Pelaksana Harian pada jabatan yang setara', 'skor' => 20.00],
            ['parent' => 'Penugasan Dalam Jabatan Nondefinitif', 'instrumen' => 'f. Tidak memiliki pengalaman penugasan dalam jabatan nondefinitif', 'skor' => 0.00],

            // Pengembangan Kompetensi
            // Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi 3 tahun terakhir
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'a. Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi dalam 3 tahun terakhir sebanyak 8 kali atau lebih', 'skor' => 100.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'b. Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi dalam 3 tahun terakhir sebanyak 6-8 kali', 'skor' => 75.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'c. Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi dalam 3 tahun terakhir sebanyak 4-6 kali', 'skor' => 50.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'd. Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi dalam 3 tahun terakhir sebanyak 1-3 kali', 'skor' => 25.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'e. Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi dalam 3 tahun terakhir sebanyak 0 kali', 'skor' => 0.00],
            // Diklat Kepemimpinan/Keahlian/Penjenjangan
            // Jumlah Sertifikasi Keahlian / Diklat Penjenjangan 3 tahun terakhir
            ['parent' => 'Diklat Kepemimpinan/Keahlian/Penjenjangan', 'instrumen' => 'f. Jumlah Sertifikasi dalam 3 tahun terakhir sebanyak 3 kali atau lebih', 'skor' => 100.00],
            ['parent' => 'Diklat Kepemimpinan/Keahlian/Penjenjangan', 'instrumen' => 'g. Jumlah Sertifikasi dalam 3 tahun terakhir sebanyak 1-2 kali', 'skor' => 50.00],
            ['parent' => 'Diklat Kepemimpinan/Keahlian/Penjenjangan', 'instrumen' => 'h. Jumlah Sertifikasi dalam 3 tahun terakhir sebanyak 0 kali', 'skor' => 0.00],
            // Penghargaan atas Capaian Kinerja
            ['parent' => 'Penghargaan atas Capaian Kinerja', 'instrumen' => 'a. Peraih penghargaan di lingkup Internasional dalam 5 tahun terakhir', 'skor' => 100.00],
            ['parent' => 'Penghargaan atas Capaian Kinerja', 'instrumen' => 'b. Peraih penghargaan di lingkup Nasional dalam 5 tahun terakhir', 'skor' => 75.00],
            ['parent' => 'Penghargaan atas Capaian Kinerja', 'instrumen' => 'c. Peraih penghargaan di lingkup lintas Instansi dalam 5 tahun terakhir', 'skor' => 50.00],
            ['parent' => 'Penghargaan atas Capaian Kinerja', 'instrumen' => 'd. Peraih penghargaan di lingkup Instansi dalam 5 tahun terakhir', 'skor' => 25.00],
            ['parent' => 'Penghargaan atas Capaian Kinerja', 'instrumen' => 'e. Tidak pernah mendapatkan penghargaan', 'skor' => 0.00],
            // Integritas/Moralitas
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'a. Tidak pernah dijatuhi hukuman disiplin dalam 5 tahun terakhir', 'skor' => 100.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'b. Pernah dijatuhi hukuman disiplin ringan dalam 5 tahun terakhir', 'skor' => 75.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'c. Pernah dijatuhi hukuman disiplin sedang dalam 5 tahun terakhir', 'skor' => 50.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'd. Pernah dijatuhi hukuman disiplin berat dalam 5 tahun terakhir', 'skor' => 25.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'e. Sedang menjalani hukuman disiplin', 'skor' => 0.00],
            // Kesesuaian Pendidikan dengan Jabatan Target
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'a. Memiliki riwayat pendidikan dalam bidang ilmu yang sesuai dengan jabatan target', 'skor' => 100.00],
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'b. Tidak Memiliki riwayat pendidikan dalam bidang ilmu yang sesuai dengan jabatan target', 'skor' => 50.00],
            // Penugasan dalam Tim Kerja
            ['parent' => 'Penugasan dalam Tim Kerja', 'instrumen' => 'a. Ketua tim kerja lingkup lintas instansi dalam 2 tahun terakhir', 'skor' => 100.00],
            ['parent' => 'Penugasan dalam Tim Kerja', 'instrumen' => 'b. Ketua tim kerja lingkup internal instansi dalam 2 tahun terakhir', 'skor' => 75.00],
            ['parent' => 'Penugasan dalam Tim Kerja', 'instrumen' => 'c. Anggota tim kerja lingkup lintas instansi dalam 2 tahun terakhir', 'skor' => 50.00],
            ['parent' => 'Penugasan dalam Tim Kerja', 'instrumen' => 'd. Anggota tim kerja lingkup internal instansi dalam 2 tahun terakhir', 'skor' => 25.00],
            ['parent' => 'Penugasan dalam Tim Kerja', 'instrumen' => 'e. Tidak mempunyai penugasan dalam tim kerja', 'skor' => 0.00],
            // Inovasi
            ['parent' => 'Inovasi', 'instrumen' => 'a. Inovasi digunakan dilevel Nasional', 'skor' => 100.00],
            ['parent' => 'Inovasi', 'instrumen' => 'b. Inovasi digunakan dilevel Instansi', 'skor' => 80.00],
            ['parent' => 'Inovasi', 'instrumen' => 'c. Inovasi digunakan dilevel unit eselon II', 'skor' => 60.00],
            ['parent' => 'Inovasi', 'instrumen' => 'd. Inovasi digunakan dilevel unit eselon III', 'skor' => 40.00],
            ['parent' => 'Inovasi', 'instrumen' => 'e. Inovasi digunakan dilevel unit eselon IV', 'skor' => 20.00],
            // Tugas Tambahan
            ['parent' => 'Tugas Tambahan', 'instrumen' => 'a. Memiliki 2 atau lebih tugas tambahan lingkup nasional dan/atau organisasi', 'skor' => 100.00],
            ['parent' => 'Tugas Tambahan', 'instrumen' => 'b. Memiliki 1 tugas tambahan lingkup nasional dan/atau organisasi', 'skor' => 80.00],
            ['parent' => 'Tugas Tambahan', 'instrumen' => 'c. Memiliki 2 atau lebih tugas tambahan lingkup unit kerja', 'skor' => 60.00],
            ['parent' => 'Tugas Tambahan', 'instrumen' => 'd. Memiliki 1 tugas tambahan lingkup unit kerja', 'skor' => 40.00],

            // Kehadiran
            ['parent' => 'Kehadiran', 'instrumen' => 'a. Pemenuhan Jam Kerja 90 s.d. 100%', 'skor' => 100.00],
            ['parent' => 'Kehadiran', 'instrumen' => 'b. Pemenuhan Jam Kerja 80 s.d. 89%', 'skor' => 80.00],
            ['parent' => 'Kehadiran', 'instrumen' => 'c. Pemenuhan Jam Kerja 70 s.d. 79%', 'skor' => 60.00],
            ['parent' => 'Kehadiran', 'instrumen' => 'd. Pemenuhan Jam Kerja 60 s.d. 69%', 'skor' => 40.00],
            ['parent' => 'Kehadiran', 'instrumen' => 'e. Pemenuhan Jam Kerja <60 %', 'skor' => 20.00],

            // Penilaian Kerja (SKP)
            ['parent' => 'Penilaian Kerja (SKP)', 'instrumen' => 'a. Sangat Baik', 'skor' => 100.00],
            ['parent' => 'Penilaian Kerja (SKP)', 'instrumen' => 'b. Baik', 'skor' => 80.00],
            ['parent' => 'Penilaian Kerja (SKP)', 'instrumen' => 'c. Butuh Perbaikan', 'skor' => 60.00],
            ['parent' => 'Penilaian Kerja (SKP)', 'instrumen' => 'd. Kurang', 'skor' => 40.00],
            ['parent' => 'Penilaian Kerja (SKP)', 'instrumen' => 'e. Sangat Kurang ', 'skor' => 20.00],

            // Umpan Balik 360 Derajat
            ['parent' => 'Umpan Balik 360 Derajat', 'instrumen' => 'a. Sangat Baik', 'skor' => 100.00],
            ['parent' => 'Umpan Balik 360 Derajat', 'instrumen' => 'b. Baik', 'skor' => 80.00],
            ['parent' => 'Umpan Balik 360 Derajat', 'instrumen' => 'c. Butuh Perbaikan', 'skor' => 60.00],
            ['parent' => 'Umpan Balik 360 Derajat', 'instrumen' => 'd. Kurang', 'skor' => 40.00],
            ['parent' => 'Umpan Balik 360 Derajat', 'instrumen' => 'e. Sangat Kurang ', 'skor' => 20.00],
        ];

        foreach ($items as $it) {
            $parent = SubIndikator::where('subindikator', $it['parent'])->first();
            if (! $parent) {
                continue;
            }

            $exists = DB::table('instrumens')
                ->where('instrumen', $it['instrumen'])
                ->where('subindikator_id', $parent->id)
                ->exists();

            $record = [
                'id' => (string) Str::uuid(),
                'instrumen' => $it['instrumen'],
                'skor' => number_format($it['skor'], 2, '.', ''),
                'subindikator_id' => $parent->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($exists) {
                DB::table('instrumens')
                    ->where('instrumen', $it['instrumen'])
                    ->where('subindikator_id', $parent->id)
                    ->update([
                        'skor' => $record['skor'],
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('instrumens')->insert($record);
            }
        }
    }
}
