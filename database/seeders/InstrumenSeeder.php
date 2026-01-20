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
            // Kualifikasi
            ['parent' => 'Kualifikasi', 'instrumen' => 'a. Sarjana Strata 3 (S3)', 'skor' => 100.00],
            ['parent' => 'Kualifikasi', 'instrumen' => 'b. Sarjana Strata (S2)', 'skor' => 90.00],
            ['parent' => 'Kualifikasi', 'instrumen' => 'c. Sarjana Strata (S1) atau Diploma IV (D.IV)', 'skor' => 80.00],
            ['parent' => 'Kualifikasi', 'instrumen' => 'd. Diploma III (D3)', 'skor' => 70.00],
            ['parent' => 'Kualifikasi', 'instrumen' => 'e. Sekolah Lanjutan Tingkat Atas (SLTA)', 'skor' => 60.00],

            // Pangkat/Golongan Ruang
            ['parent' => 'Pangkat/Golongan Ruang', 'instrumen' => 'a. Memiliki Pangkat/Golongan di atas persyaratan pangkat minimal', 'skor' => 100.00],
            ['parent' => 'Pangkat/Golongan Ruang', 'instrumen' => 'b. Memiliki Pangkat/Golongan sesuai dengan persyaratan pangkat minimal 5 tahun', 'skor' => 80.00],
            ['parent' => 'Pangkat/Golongan Ruang', 'instrumen' => 'c. Memiliki Pangkat/Golongan sesuai dengan pangkat minimal selama 4 tahun', 'skor' => 60.00],
            ['parent' => 'Pangkat/Golongan Ruang', 'instrumen' => 'd. Memiliki Pangkat/Golongan sesuai dengan pangkat minimal selama 3 tahun', 'skor' => 40.00],
            ['parent' => 'Pangkat/Golongan Ruang', 'instrumen' => 'e. Memiliki Pangkat/Golongan sesuai dengan pangkat minimal selama 2 tahun atau kurang', 'skor' => 20.00],
            // Masa kerja
            ['parent' => 'Masa kerja', 'instrumen' => 'a. Memiliki masa kerja 25 tahun keatas', 'skor' => 100.00],
            ['parent' => 'Masa kerja', 'instrumen' => 'b. Memiliki masa kerja >15 s.d 25 tahun', 'skor' => 80.00],
            ['parent' => 'Masa kerja', 'instrumen' => 'c. Memiliki masa kerja >10 s.d 15 tahun', 'skor' => 60.00],
            ['parent' => 'Masa kerja', 'instrumen' => 'd. Memiliki masa kerja >5 s.d 10 tahun', 'skor' => 40.00],
            ['parent' => 'Masa kerja', 'instrumen' => 'e. Memiliki masa kerja 0 s.d 5 tahun', 'skor' => 20.00],
            // Pengalaman Jabatan (Rekam Jejak Jabatan)
            ['parent' => 'Pengalaman Jabatan', 'instrumen' => 'a. Pernah berpindah unit kerja lebih dari 7 kali', 'skor' => 100.00],
            ['parent' => 'Pengalaman Jabatan', 'instrumen' => 'b. Pernah berpindah unit kerja 6 sd 7 kali', 'skor' => 80.00],
            ['parent' => 'Pengalaman Jabatan', 'instrumen' => 'c. Pernah berpindah unit kerja 4 sd 5 kali', 'skor' => 60.00],
            ['parent' => 'Pengalaman Jabatan', 'instrumen' => 'd. Pernah berpindah unit kerja sebanyak 3 kali', 'skor' => 40.00],
            ['parent' => 'Pengalaman Jabatan', 'instrumen' => 'e. Pernah berpindah unit kerja sebanyak 2 kali', 'skor' => 20.00],
            // Diklat Kepemimpinan
            ['parent' => 'Diklat Kepemimpinan', 'instrumen' => 'a. Sudah mengikuti Diklat Kepemimpinan sesuai dengan persyaratan jabatan target', 'skor' => 100.00],
            ['parent' => 'Diklat Kepemimpinan', 'instrumen' => 'b. Sedang mengikuti Diklat Kepemimpinan sesuai dengan persyaratan jabatan target', 'skor' => 50.00],
            ['parent' => 'Diklat Kepemimpinan', 'instrumen' => 'c. Belum mengikuti Diklat Kepemimpinan sesuai dengan persyaratan jabatan target', 'skor' => 0.00],

            // Pengembangan Kompetensi
            // Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi 3 tahun terakhir
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi dalam 3 tahun terakhir sebanyak 8 kali atau lebih', 'skor' => 100.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi dalam 3 tahun terakhir sebanyak 6-8 kali', 'skor' => 75.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi dalam 3 tahun terakhir sebanyak 4-6 kali', 'skor' => 50.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi dalam 3 tahun terakhir sebanyak 1-3 kali', 'skor' => 25.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'Jumlah Sertifikat Kepesertaan Pelatihan dan Pengembangan Kompetensi dalam 3 tahun terakhir sebanyak 0 kali', 'skor' => 0.00],
            // Jumlah Sertifikasi Keahlian / Diklat Penjenjangan 3 tahun terakhir
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'Jumlah Sertifikasi Keahlian/Diklat Penjenjangan dalam 3 tahun terakhir sebanyak 3 kali atau lebih', 'skor' => 100.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'Jumlah Sertifikasi Keahlian/Diklat Penjenjangan dalam 3 tahun terakhir sebanyak 1-2 kali', 'skor' => 50.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'Jumlah Sertifikasi Keahlian/Diklat Penjenjangan dalam 3 tahun terakhir sebanyak 0 kali', 'skor' => 0.00],
            // Penghargaan
            ['parent' => 'Penghargaan', 'instrumen' => 'Peraih penghargaan di lingkup Internasional dalam 5 tahun terakhir', 'skor' => 100.00],
            ['parent' => 'Penghargaan', 'instrumen' => 'Peraih penghargaan di lingkup Nasional dalam 5 tahun terakhir', 'skor' => 75.00],
            ['parent' => 'Penghargaan', 'instrumen' => 'Peraih penghargaan di lingkup lintas Instansi dalam 5 tahun terakhir', 'skor' => 50.00],
            ['parent' => 'Penghargaan', 'instrumen' => 'Peraih penghargaan di lingkup Instansi dalam 5 tahun terakhir', 'skor' => 25.00],
            ['parent' => 'Penghargaan', 'instrumen' => 'Tidak pernah mendapatkan penghargaan', 'skor' => 0.00],
            // Integritas/Moralitas
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'Tidak pernah dijatuhi hukuman disiplin dalam 5 tahun terakhir', 'skor' => 100.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'Pernah dijatuhi hukuman disiplin ringan dalam 5 tahun terakhir', 'skor' => 75.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'Pernah dijatuhi hukuman disiplin sedang dalam 5 tahun terakhir', 'skor' => 50.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'Pernah dijatuhi hukuman disiplin berat dalam 5 tahun terakhir', 'skor' => 25.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'Sedang menjalani hukuman disiplin', 'skor' => 0.00],
            // Kesesuaian Pendidikan dengan Jabatan Target
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'a. Strata S.3 atau Strata 2 (S.1 dan S.2 jurusan linier dan sesuai dengan SKJ jabatan target)', 'skor' => 100.00],
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'b. Strata 2 (S.1 dan S.2 jurusan tidak linier dan salah satunya sesuai dengan SKJ jabatan target)', 'skor' => 80.00],
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'c. Strata 2 (S.1 dan S.2 jurusan linier, namun tidak sesuai dengan SKJ jabatan target)', 'skor' => 60.00],
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'd. Strata 2 (S.1 dan S.2 jurusan tidak linier dan tidak sesuai dengan SKJ jabatan target)', 'skor' => 40.00],
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'e. Strata 1 jurusan sesuai dengan SKJ jabatan target', 'skor' => 20.00],
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'f. Strata 1 dan D3, jurusan tidak sesuai dengan SKJ jabatan target', 'skor' => 10.00],
            // Penugasan dalam Tim Kerja
            ['parent' => 'Penugasan dalam Tim Kerja', 'instrumen' => 'Ketua tim kerja lingkup lintas instansi dalam 2 tahun terakhir', 'skor' => 100.00],
            ['parent' => 'Penugasan dalam Tim Kerja', 'instrumen' => 'Ketua tim kerja lingkup internal instansi dalam 2 tahun terakhir', 'skor' => 75.00],
            ['parent' => 'Penugasan dalam Tim Kerja', 'instrumen' => 'Anggota tim kerja lingkup lintas instansi dalam 2 tahun terakhir', 'skor' => 50.00],
            ['parent' => 'Penugasan dalam Tim Kerja', 'instrumen' => 'Anggota tim kerja lingkup internal instansi dalam 2 tahun terakhir', 'skor' => 25.00],
            ['parent' => 'Penugasan dalam Tim Kerja', 'instrumen' => 'Tidak mempunyai penugasan dalam tim kerja', 'skor' => 0.00],
            // Pertimbangan Atasan
            ['parent' => 'Pertimbangan Atasan', 'instrumen' => 'a. Sangat Mendukung', 'skor' => 100.00],
            ['parent' => 'Pertimbangan Atasan', 'instrumen' => 'b. Mendukung', 'skor' => 70.00],
            ['parent' => 'Pertimbangan Atasan', 'instrumen' => 'c. Kurang Mendukung', 'skor' => 40.00],
            ['parent' => 'Pertimbangan Atasan', 'instrumen' => 'd. Tidak Mendukung', 'skor' => 20.00],

            // Inovasi
            ['parent' => 'Inovasi', 'instrumen' => 'a. Inovasi digunakan dilevel Nasional', 'skor' => 100.00],
            ['parent' => 'Inovasi', 'instrumen' => 'b. Inovasi digunakan dilevel Instansi', 'skor' => 80.00],
            ['parent' => 'Inovasi', 'instrumen' => 'c. Inovasi digunakan dilevel unit eselon II', 'skor' => 60.00],
            ['parent' => 'Inovasi', 'instrumen' => 'd. Inovasi digunakan dilevel unit eselon III', 'skor' => 40.00],
            ['parent' => 'Inovasi', 'instrumen' => 'e. Inovasi digunakan dilevel unit eselon IV', 'skor' => 20.00],
            // Partisipasi dalam Organisasi
            ['parent' => 'Partisipasi dalam Organisasi', 'instrumen' => 'a. Anggota Tim/Pokja Nasional', 'skor' => 100.00],
            ['parent' => 'Partisipasi dalam Organisasi', 'instrumen' => 'b. Ketua Tim/Pokja Instansi', 'skor' => 80.00],
            ['parent' => 'Partisipasi dalam Organisasi', 'instrumen' => 'c. Anggota Tim/Pokja Instansi', 'skor' => 60.00],
            ['parent' => 'Partisipasi dalam Organisasi', 'instrumen' => 'd. Ketua Tim/Pokja Unit Eselon II', 'skor' => 40.00],
            ['parent' => 'Partisipasi dalam Organisasi', 'instrumen' => 'e. Anggota Tim/Pokja Unit Eselon II', 'skor' => 20.00],

            // Tugas Tambahan
            ['parent' => 'Tugas Tambahan', 'instrumen' => 'a. Memiliki 2 atau lebih tugas tambahan lingkup nasional dan/atau organisasi', 'skor' => 100.00],
            ['parent' => 'Tugas Tambahan', 'instrumen' => 'b. Memiliki 1 tugas tambahan lingkup nasional dan/atau organisasi', 'skor' => 80.00],
            ['parent' => 'Tugas Tambahan', 'instrumen' => 'c. Memiliki 2 atau lebih tugas tambahan lingkup unit kerja', 'skor' => 60.00],
            ['parent' => 'Tugas Tambahan', 'instrumen' => 'd. Memiliki 1 tugas tambahan lingkup unit kerja', 'skor' => 40.00],

            // Disiplin
            ['parent' => 'Disiplin', 'instrumen' => 'a. Pemenuhan Jam Kerja 90 s.d. 100%', 'skor' => 100.00],
            ['parent' => 'Disiplin', 'instrumen' => 'b. Pemenuhan Jam Kerja 80 s.d. 89%', 'skor' => 80.00],
            ['parent' => 'Disiplin', 'instrumen' => 'c. Pemenuhan Jam Kerja 70 s.d. 79%', 'skor' => 60.00],
            ['parent' => 'Disiplin', 'instrumen' => 'd. Pemenuhan Jam Kerja 60 s.d. 69%', 'skor' => 40.00],
            ['parent' => 'Disiplin', 'instrumen' => 'e. Pemenuhan Jam Kerja <60 %', 'skor' => 20.00],
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
