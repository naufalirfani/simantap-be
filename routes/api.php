<?php

use App\Http\Controllers\IndikatorController;
use App\Http\Controllers\InstrumenController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PengembanganStatistikController;
use App\Http\Controllers\PetaJabatanController;
use App\Http\Controllers\StatistikController;
use App\Http\Controllers\SubIndikatorController;
use App\Http\Controllers\DaftarKotakController;
use App\Http\Controllers\PenilaianController;
use App\Http\Controllers\RiwayatAsesmenController;
use App\Http\Controllers\StandarKompetensiMskController;
use App\Http\Controllers\SyaratSuksesiController;
use App\Http\Controllers\LampiranAsesmenController;
use Illuminate\Support\Facades\Route;

// Apply API token, logging and IP whitelist middleware to all API routes
Route::middleware(['log.api.requests', 'verify.api.token', 'whitelist.ip'])->group(function () {

// Indikator Routes
Route::apiResource('indikators', IndikatorController::class);

// SubIndikator Routes
Route::apiResource('subindikators', SubIndikatorController::class);
// Bulk update bobot for subindikators; updates parent indikator bobot automatically
Route::post('subindikators/bulk-bobot', [SubIndikatorController::class, 'bulkUpdateBobot']);

// Instrumen Routes
Route::apiResource('instrumens', InstrumenController::class);

// Peta Jabatan Routes
Route::get('peta-jabatan', [PetaJabatanController::class, 'index']);
Route::get('peta-jabatan/tree', [PetaJabatanController::class, 'tree']);
Route::get('peta-jabatan/tree-by-unit-kerja', [PetaJabatanController::class, 'treeByUnitKerja']);
Route::post('peta-jabatan/sync', [PetaJabatanController::class, 'sync']);
Route::get('peta-jabatan/{id}', [PetaJabatanController::class, 'show']);

// Pegawai Routes
Route::get('pegawai', [PegawaiController::class, 'index']);
Route::post('pegawai/sync', [PegawaiController::class, 'sync']);
Route::get('pegawai/rekomendasi/{peta_jabatan_id}', [PegawaiController::class, 'recommend']);
Route::get('pegawai/{nip}', [PegawaiController::class, 'show']);

// Penilaian Routes
// Custom routes must be declared BEFORE apiResource to prevent {penilaian} wildcard from catching them
Route::post('penilaians/sync', [PenilaianController::class, 'sync']);
Route::get('penilaians/sync-status', [PenilaianController::class, 'syncStatus']);
Route::post('penilaians/bulk', [PenilaianController::class, 'bulk']);
Route::apiResource('penilaians', PenilaianController::class);
Route::get('riwayat-asesmens/nama-asesmen', [RiwayatAsesmenController::class, 'uniqueNamaAsesmen']);
Route::get('riwayat-asesmens/pegawai/{pegawai_id}', [RiwayatAsesmenController::class, 'showByPegawai']);
Route::apiResource('riwayat-asesmens', RiwayatAsesmenController::class);
// Standar Kompetensi MSK CRUD
Route::apiResource('standar-kompetensi-msk', StandarKompetensiMskController::class);
// Bulk update standar kompetensi
Route::post('standar-kompetensi-msk/bulk', [StandarKompetensiMskController::class, 'bulkUpdate']);

// Syarat Suksesi Routes
Route::apiResource('syarat-suksesi', SyaratSuksesiController::class);

// Statistik Routes
Route::get('statistik', [StatistikController::class, 'index']);
Route::post('statistik/sync', [StatistikController::class, 'sync']);

// Pengembangan Statistik Routes
Route::get('pengembangan/statistik', [PengembanganStatistikController::class, 'index']);
// Daftar Kotak (intervals + kotak) Routes
Route::get('daftar-kotak', [DaftarKotakController::class, 'index']);
Route::post('daftar-kotak', [DaftarKotakController::class, 'store']);

// Lampiran Asesmen Routes
// Custom routes must be declared BEFORE apiResource to prevent {lampiran-asesmen} wildcard from catching them
Route::get('lampiran-asesmens/{id}/download', [LampiranAsesmenController::class, 'download']);
Route::post('lampiran-asesmens/by-pegawai-and-nama', [LampiranAsesmenController::class, 'updateByPegawaiAndNama']);
Route::apiResource('lampiran-asesmens', LampiranAsesmenController::class);

});
