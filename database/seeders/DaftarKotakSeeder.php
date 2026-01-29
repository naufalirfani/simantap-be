<?php

namespace Database\Seeders;

use App\Models\DaftarKotak;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DaftarKotakSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $config = [
            'intervals' => [
                'potensial' => [
                    ['min' => 0, 'max' => 60, 'label' => '0-60'],
                    ['min' => 60, 'max' => 80, 'label' => '60-80'],
                    ['min' => 80, 'max' => 100, 'label' => '80-100'],
                ],
                'kinerja' => [
                    ['min' => 0, 'max' => 60, 'label' => '0-60'],
                    ['min' => 60, 'max' => 80, 'label' => '60-80'],
                    ['min' => 80, 'max' => 100, 'label' => '80-100'],
                ],
            ],
            'kotak' => [
                [
                    'id' => 1,
                    'kategori' => 'Kinerja di Bawah Ekspektasi dan Potensial Rendah',
                    'warna' => '#EF4444',
                    'potensialRange' => ['min' => 0, 'max' => 60],
                    'kinerjaRange' => ['min' => 0, 'max' => 60],
                    'rekomendasi' => [
                        'Diproses sesuai ketentuan peraturan perundangan',
                    ],
                ],
                [
                    'id' => 2,
                    'kategori' => 'Kinerja Sesuai Ekspektasi dan Potensial Rendah',
                    'warna' => '#F97316',
                    'potensialRange' => ['min' => 0, 'max' => 60],
                    'kinerjaRange' => ['min' => 60, 'max' => 80],
                    'rekomendasi' => [
                        'Bimbingan kinerja',
                        'Pengembangan kompetensi',
                        'Penempatan yang sesuai',
                    ],
                ],
                [
                    'id' => 3,
                    'kategori' => 'Kinerja di Bawah Ekspektasi dan Potensial Menengah',
                    'warna' => '#F59E0B',
                    'potensialRange' => ['min' => 60, 'max' => 80],
                    'kinerjaRange' => ['min' => 0, 'max' => 60],
                    'rekomendasi' => [
                        'Bimbingan kinerja',
                        'Konseling kinerja',
                        'Pengembangan kompetensi',
                        'Penempatan yang sesuai',
                    ],
                ],
                [
                    'id' => 4,
                    'kategori' => 'Kinerja di Atas Ekspektasi dan Potensial Rendah',
                    'warna' => '#F59E0B',
                    'potensialRange' => ['min' => 0, 'max' => 60],
                    'kinerjaRange' => ['min' => 80, 'max' => 100],
                    'rekomendasi' => [
                        'Rotasi',
                        'Pengembangan kompetensi',
                    ],
                ],
                [
                    'id' => 5,
                    'kategori' => 'Kinerja Sesuai Ekspektasi dan Potensial Menengah',
                    'warna' => '#EAB308',
                    'potensialRange' => ['min' => 60, 'max' => 80],
                    'kinerjaRange' => ['min' => 60, 'max' => 80],
                    'rekomendasi' => [
                        'Penempatan yang sesuai',
                        'Bimbingan kinerja',
                        'Pengembangan kompetensi',
                    ],
                ],
                [
                    'id' => 6,
                    'kategori' => 'Kinerja di Bawah Ekspektasi dan Potensial Tinggi',
                    'warna' => '#84CC16',
                    'potensialRange' => ['min' => 80, 'max' => 100],
                    'kinerjaRange' => ['min' => 0, 'max' => 60],
                    'rekomendasi' => [
                        'Penempatan yang sesuai',
                        'Bimbingan kinerja',
                        'Konseling kinerja',
                    ],
                ],
                [
                    'id' => 7,
                    'kategori' => 'Kinerja di Atas Ekspektasi dan Potensial Menengah',
                    'warna' => '#84CC16',
                    'potensialRange' => ['min' => 60, 'max' => 80],
                    'kinerjaRange' => ['min' => 80, 'max' => 100],
                    'rekomendasi' => [
                        'Dipertahankan',
                        'Masuk Kelompok Rencana Suksesi Instansi',
                        'Rotasi/Pengayaan jabatan',
                        'Pengembangan kompetensi',
                        'Tugas belajar',
                    ],
                ],
                [
                    'id' => 8,
                    'kategori' => 'Kinerja Sesuai Ekspektasi dan Potensial Tinggi',
                    'warna' => '#22C55E',
                    'potensialRange' => ['min' => 80, 'max' => 100],
                    'kinerjaRange' => ['min' => 60, 'max' => 80],
                    'rekomendasi' => [
                        'Dipertahankan',
                        'Masuk Kelompok Rencana Suksesi Instansi',
                        'Rotasi/Perluasan jabatan',
                        'Bimbingan kinerja',
                    ],
                ],
                [
                    'id' => 9,
                    'kategori' => 'Kinerja di Atas Ekspektasi dan Potensial Tinggi',
                    'warna' => '#10B981',
                    'potensialRange' => ['min' => 80, 'max' => 100],
                    'kinerjaRange' => ['min' => 80, 'max' => 100],
                    'rekomendasi' => [
                        'Dipromosikan dan dipertahankan',
                        'Masuk Kelompok Rencana Suksesi Instansi/Nasional',
                        'Penghargaan',
                    ],
                ],
            ],
        ];

        DaftarKotak::truncate();
        DaftarKotak::create($config);
    }
}
