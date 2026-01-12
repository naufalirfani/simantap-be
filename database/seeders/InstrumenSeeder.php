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
            ['parent' => 'Kualifikasi', 'instrumen' => 'a. Sarjana Strata 3 (S.3)', 'skor' => 100.00],
            ['parent' => 'Kualifikasi', 'instrumen' => 'b. Sarjana Strata (s.2)', 'skor' => 80.00],
            ['parent' => 'Kualifikasi', 'instrumen' => 'c. Sarjana Strata (s.1) atau Diploma IV (D.IV)', 'skor' => 60.00],
            ['parent' => 'Kualifikasi', 'instrumen' => 'd. Diploma III (D.3)', 'skor' => 40.00],
            ['parent' => 'Kualifikasi', 'instrumen' => 'e. Sekolah Lanjutan Tingkat Atas (SLTA)', 'skor' => 20.00],

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
            // Pengalaman dalam Organisasi (Rekam Jejak Jabatan)
            ['parent' => 'Pengalaman dalam Organisasi', 'instrumen' => 'a. Pernah berpindah unit kerja lebih dari 7 kali', 'skor' => 100.00],
            ['parent' => 'Pengalaman dalam Organisasi', 'instrumen' => 'b. Pernah berpindah unit kerja 6 sd 7 kali', 'skor' => 80.00],
            ['parent' => 'Pengalaman dalam Organisasi', 'instrumen' => 'c. Pernah berpindah unit kerja 4 sd 5 kali', 'skor' => 60.00],
            ['parent' => 'Pengalaman dalam Organisasi', 'instrumen' => 'd. Pernah berpindah unit kerja sebanyak 3 kali', 'skor' => 40.00],
            ['parent' => 'Pengalaman dalam Organisasi', 'instrumen' => 'e. Pernah berpindah unit kerja sebanyak 2 kali', 'skor' => 20.00],
            // Diklat Kepemimpinan
            ['parent' => 'Diklat Kepemimpinan', 'instrumen' => 'a. Sudah mengikuti Diklat Kepemimpinan sesuai dengan persyaratan jabatan target', 'skor' => 100.00],
            ['parent' => 'Diklat Kepemimpinan', 'instrumen' => 'b. Sedang mengikuti Diklat Kepemimpinan sesuai dengan persyaratan jabatan target', 'skor' => 50.00],
            ['parent' => 'Diklat Kepemimpinan', 'instrumen' => 'c. Belum mengikuti Diklat Kepemimpinan sesuai dengan persyaratan jabatan target', 'skor' => 0.00],

            // Pengembangan Kompetensi
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'a. Pernah mengikuti pengembangan kopetensi sebanyak lebih dari 10 kali', 'skor' => 100.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'b. Pernah mengikuti pengembangan kompetensi sebanyak 8 sd. 9 kali', 'skor' => 80.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'c. Pernah mengikuti pengembangan kompetensi sebanyak 6 sd. 7 kali', 'skor' => 60.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'd. Pernah mengikuti pengembangan kompetensi sebanyak 4 sd. 5 kali', 'skor' => 40.00],
            ['parent' => 'Pengembangan Kompetensi', 'instrumen' => 'e. Pernah mengikuti pengembangan kompetensi sebanyak 3 kali atau kurang', 'skor' => 20.00],
            // Penghargaan
            ['parent' => 'Penghargaan', 'instrumen' => 'a. Penghargaan yang diberikan oleh Presiden atau negara (ditandatangani oleh Presiden atau Menteri)', 'skor' => 100.00],
            ['parent' => 'Penghargaan', 'instrumen' => 'b. Penghargaan yang diberikan oleh Instansi/organisasi lain (ditandatangani oleh pimpinan organisasi)', 'skor' => 80.00],
            ['parent' => 'Penghargaan', 'instrumen' => 'c. Penghargaan yang diberikan oleh organisasi (ditandatangani oleh pimpinan organisasi)', 'skor' => 60.00],
            ['parent' => 'Penghargaan', 'instrumen' => 'd. Penghargaan yang diberikan oleh organisasi (ditandatangani oleh pimpinan unit Eselon I)', 'skor' => 40.00],
            ['parent' => 'Penghargaan', 'instrumen' => 'e. Penghargaan yang diberikan oleh unit kerja (ditandatangani oleh pimpinan unit Eselon III)', 'skor' => 20.00],
            // Integritas/Moralitas
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'a. Tidak pernah dijatuhi hukuman disiplin dalam 15 tahun terakhir', 'skor' => 100.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'b. Tidak pernah dijatuhi hukuman disiplin dalam 12 tahun terakhir', 'skor' => 80.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'c. Tidak pernah dijatuhi hukuman disiplin dalam 10 tahun terakhir', 'skor' => 60.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'd. Tidak pernah dijatuhi hukuman disiplin dalam 8 tahun terakhir', 'skor' => 40.00],
            ['parent' => 'Integritas/Moralitas', 'instrumen' => 'e. Tidak pernah dijatuhi hukuman disiplin dalam 5 tahun terakhir', 'skor' => 20.00],
            // Kesesuaian Pendidikan dengan Jabatan Target
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'a. Strata S.3 atau Strata 2 (S.1 dan S.2 jurusan linier dan sesuai dengan SKJ jabatan target)', 'skor' => 100.00],
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'b. Strata 2 (S.1 dan S.2 jurusan tidak linier dan salah satunya sesuai dengan SKJ jabatan target)', 'skor' => 80.00],
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'c. Strata 2 (S.1 dan S.2 jurusan linier, namun tidak sesuai dengan SKJ jabatan target)', 'skor' => 60.00],
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'd. Strata 2 (S.1 dan S.2 jurusan tidak linier dan tidak sesuai dengan SKJ jabatan target)', 'skor' => 40.00],
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'e. Strata 1 jurusan sesuai dengan SKJ jabatan target', 'skor' => 20.00],
            ['parent' => 'Kesesuaian Pendidikan dengan Jabatan Target', 'instrumen' => 'f. Strata 1 dan D3, jurusan tidak sesuai dengan SKJ jabatan target', 'skor' => 10.00],
            // Pengalaman Organisasi (Pertimbangan lainnya)
            ['parent' => 'Pengalaman Organisasi', 'instrumen' => 'a. Menjadi Ketua organisasi dalam lingkup nasional', 'skor' => 100.00],
            ['parent' => 'Pengalaman Organisasi', 'instrumen' => 'b. Menjadi pengurus organisasi dalam lingkup nasional', 'skor' => 80.00],
            ['parent' => 'Pengalaman Organisasi', 'instrumen' => 'c. Menjadi Ketua organisasi dalam lingkup wilayah provinsi', 'skor' => 60.00],
            ['parent' => 'Pengalaman Organisasi', 'instrumen' => 'd. Menjadi pengurus organisasi dalam lingkup wilayah provinsi', 'skor' => 40.00],
            ['parent' => 'Pengalaman Organisasi', 'instrumen' => 'e. Menjadi Ketua organisasi dalam lingkup wilayah Kota/Kabupaten', 'skor' => 20.00],
            ['parent' => 'Pengalaman Organisasi', 'instrumen' => 'f. Menjadi pengurus organisasi dalam lingkup wilayah Kota/Kabupaten', 'skor' => 10.00],
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
