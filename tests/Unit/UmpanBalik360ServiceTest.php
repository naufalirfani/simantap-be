<?php

namespace Tests\Unit;

use App\Services\PenilaianSyncService;
use App\Services\UmpanBalik360Service;
use Tests\TestCase;

class UmpanBalik360ServiceTest extends TestCase
{
    private function getSampleInstrumens(): \Illuminate\Support\Collection
    {
        return collect([
            (object) ['instrumen' => 'a. Sangat Baik (91 - 100)', 'skor' => 100.00],
            (object) ['instrumen' => 'b. Baik (80 - 90)', 'skor' => 80.00],
            (object) ['instrumen' => 'c. Butuh Perbaikan (60 - 79)', 'skor' => 60.00],
            (object) ['instrumen' => 'd. Kurang (40 - 59)', 'skor' => 40.00],
            (object) ['instrumen' => 'e. Sangat Kurang (0 - 39)', 'skor' => 20.00],
        ]);
    }

    public function test_resolve_nilai_from_instrumen_with_standard_ranges(): void
    {
        $service = new UmpanBalik360Service();
        $instrumens = $this->getSampleInstrumens();

        // 92 -> Sangat Baik (100.00)
        $this->assertEquals(100.00, $service->resolveNilaiFromInstrumen(92.0, $instrumens));
        $this->assertEquals(100.00, $service->resolveNilaiFromInstrumen(100.0, $instrumens));
        $this->assertEquals(100.00, $service->resolveNilaiFromInstrumen(91.0, $instrumens));

        // 80 - 90 -> Baik (80.00)
        $this->assertEquals(80.00, $service->resolveNilaiFromInstrumen(85.0, $instrumens));
        $this->assertEquals(80.00, $service->resolveNilaiFromInstrumen(80.0, $instrumens));
        $this->assertEquals(80.00, $service->resolveNilaiFromInstrumen(90.0, $instrumens));

        // 60 - 79 -> Butuh Perbaikan (60.00)
        $this->assertEquals(60.00, $service->resolveNilaiFromInstrumen(70.0, $instrumens));
        $this->assertEquals(60.00, $service->resolveNilaiFromInstrumen(60.0, $instrumens));
        $this->assertEquals(60.00, $service->resolveNilaiFromInstrumen(79.0, $instrumens));

        // 40 - 59 -> Kurang (40.00)
        $this->assertEquals(40.00, $service->resolveNilaiFromInstrumen(50.0, $instrumens));
        $this->assertEquals(40.00, $service->resolveNilaiFromInstrumen(40.0, $instrumens));
        $this->assertEquals(40.00, $service->resolveNilaiFromInstrumen(59.0, $instrumens));

        // 0 - 39 -> Sangat Kurang (20.00)
        $this->assertEquals(20.00, $service->resolveNilaiFromInstrumen(30.0, $instrumens));
        $this->assertEquals(20.00, $service->resolveNilaiFromInstrumen(0.0, $instrumens));
        $this->assertEquals(20.00, $service->resolveNilaiFromInstrumen(39.0, $instrumens));
    }

    public function test_resolve_nilai_from_instrumen_handles_float_gaps(): void
    {
        $service = new UmpanBalik360Service();
        $instrumens = $this->getSampleInstrumens();

        // 90.8 rounds to 91 -> Sangat Baik (100)
        $this->assertEquals(100.00, $service->resolveNilaiFromInstrumen(90.8, $instrumens));

        // 90.2 rounds to 90 -> Baik (80)
        $this->assertEquals(80.00, $service->resolveNilaiFromInstrumen(90.2, $instrumens));

        // 79.6 rounds to 80 -> Baik (80)
        $this->assertEquals(80.00, $service->resolveNilaiFromInstrumen(79.6, $instrumens));

        // 79.4 rounds to 79 -> Butuh Perbaikan (60)
        $this->assertEquals(60.00, $service->resolveNilaiFromInstrumen(79.4, $instrumens));
    }

    public function test_resolve_nilai_from_instrumen_returns_null_when_empty(): void
    {
        $service = new UmpanBalik360Service();

        $this->assertNull($service->resolveNilaiFromInstrumen(92.0, collect()));
        $this->assertNull($service->resolveNilaiFromInstrumen(92.0, null));
        $this->assertNull($service->resolveNilaiFromInstrumen(92.0, []));
    }

    public function test_penilaian_sync_service_get_nilai_umpan_balik_360(): void
    {
        $syncService = new PenilaianSyncService();
        $instrumens = $this->getSampleInstrumens();

        $riwayat = [
            [
                'periode' => '2026-06',
                'nilai_akhir' => 85.0,
            ],
            [
                'periode' => '2026-07',
                'nilai_akhir' => 92.0, // latest period
            ],
        ];

        // Should pick latest period (2026-07 with nilai_akhir 92) and resolve to 100.00
        $nilai = $syncService->getNilaiUmpanBalik360($riwayat, $instrumens);
        $this->assertEquals(100.00, $nilai);

        // If no instrumens, should return raw latest nilai_akhir
        $nilaiRaw = $syncService->getNilaiUmpanBalik360($riwayat, collect());
        $this->assertEquals(92.00, $nilaiRaw);

        // If empty riwayat, should return null
        $this->assertNull($syncService->getNilaiUmpanBalik360([], $instrumens));
    }
}

