<?php

use App\Http\Controllers\IndikatorController;
use App\Http\Controllers\InstrumenController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PetaJabatanController;
use App\Http\Controllers\StatistikController;
use App\Http\Controllers\SubIndikatorController;
use Illuminate\Support\Facades\Route;

// Apply API token, logging and IP whitelist middleware to all API routes
Route::middleware(['log.api.requests', 'verify.api.token', 'whitelist.ip'])->group(function () {

// Indikator Routes
Route::apiResource('indikators', IndikatorController::class);

// SubIndikator Routes
Route::apiResource('subindikators', SubIndikatorController::class);

// Instrumen Routes
Route::apiResource('instrumens', InstrumenController::class);

// Peta Jabatan Routes
Route::get('peta-jabatan', [PetaJabatanController::class, 'index']);
Route::get('peta-jabatan/tree', [PetaJabatanController::class, 'tree']);
Route::post('peta-jabatan/sync', [PetaJabatanController::class, 'sync']);
Route::get('peta-jabatan/{id}', [PetaJabatanController::class, 'show']);

// Pegawai Routes
Route::get('pegawai', [PegawaiController::class, 'index']);
Route::post('pegawai/sync', [PegawaiController::class, 'sync']);
Route::get('pegawai/{nip}', [PegawaiController::class, 'show']);

// Statistik Routes
Route::get('statistik', [StatistikController::class, 'index']);
Route::post('statistik/sync', [StatistikController::class, 'sync']);
});
