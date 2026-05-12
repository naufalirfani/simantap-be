<?php

use App\Http\Controllers\PengajuanPenilaianController;
use Illuminate\Support\Facades\Route;

// Web routes - intentionally empty for production API backend
// All API routes are defined in routes/api.php

// Keep preview URL under /api path but serve via web middleware (without API CORS middleware).
Route::get('/api/pengajuan-penilaians/{id}/preview', [PengajuanPenilaianController::class, 'preview'])
	->name('pengajuan-penilaians.preview');
