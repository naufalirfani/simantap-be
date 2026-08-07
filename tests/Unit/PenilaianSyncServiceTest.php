<?php

namespace Tests\Unit;

use App\Services\PenilaianSyncService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PenilaianSyncServiceTest extends TestCase
{
    public function test_get_nilai_diklat_kepemimpinan_diklat_fungsional_ignores_tanggal_sertifikat(): void
    {
        $service = new PenilaianSyncService();

        // Instruments tier mocking
        $instrumens = collect([
            (object) ['instrumen' => 'a. Jumlah Sertifikasi dalam 3 tahun terakhir sebanyak 3 kali atau lebih', 'skor' => 100.0],
            (object) ['instrumen' => 'b. Jumlah Sertifikasi dalam 3 tahun terakhir sebanyak 1-2 kali', 'skor' => 80.0],
            (object) ['instrumen' => 'c. Jumlah Sertifikasi dalam 3 tahun terakhir sebanyak 0 kali', 'skor' => 0.0],
        ]);

        // Diklat fungsional record without tanggalSertifikat or with old tanggalSertifikat (over 3 years ago)
        $riwayatSertifikasi = [
            [
                'jenisKursusSertifikat' => 'DIKLAT FUNGSIONAL',
                'namaKursus' => 'Diklat Fungsional A',
                'tanggalSertifikat' => '01-01-2010', // Very old date
            ],
            [
                'jenisKursusSertifikat' => 'DIKLAT FUNGSIONAL',
                'namaKursus' => 'Diklat Fungsional B',
                // No tanggalSertifikat
            ],
        ];

        // Normal sertifikasi (within 3 years)
        $riwayatDiklat = [
            [
                'jenisKursusSertifikat' => 'DIKLAT STRUKTURAL',
                'namaKursus' => 'Diklat Pim III',
            ],
        ];

        // Total count should be 2 (fungsional) + 1 (diklat) = 3 -> skor 100.0
        $nilai = $service->getNilaiDiklatKepemimpinan($riwayatDiklat, $riwayatSertifikasi, $instrumens);

        $this->assertEquals(100.0, $nilai);
    }

    public function test_record_api_hit_progress_does_not_throw_exception(): void
    {
        $service = new PenilaianSyncService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('recordApiHitProgress');
        $method->setAccessible(true);

        // Should execute smoothly without throwing an undefined method or DB exception
        $this->expectNotToPerformAssertions();
        $method->invoke($service, 'SKP API', '199001012020011001');
    }
}
