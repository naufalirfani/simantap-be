<?php

namespace Database\Seeders;

use App\Models\JenisJabatan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JenisJabatanSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $items = [
            'Jabatan Pimpinan Tinggi Utama',
            'Jabatan Pimpinan Tinggi Madya',
            'Jabatan Pimpinan Tinggi Pratama',
            'Jabatan Administrator',
            'Jabatan Pengawas',
            'Jabatan Fungsional Utama',
            'Jabatan Fungsional Madya',
            'Jabatan Fungsional Muda',
            'Jabatan Fungsional Pertama',
            'Jabatan Fungsional Penyelia',
            'Jabatan Fungsional Mahir',
            'Jabatan Fungsional Terampil',
            'Jabatan Fungsional Pemula',
            'Jabatan Pelaksana',
        ];

        foreach ($items as $name) {
            JenisJabatan::firstOrCreate(['name' => $name]);
        }
    }
}
