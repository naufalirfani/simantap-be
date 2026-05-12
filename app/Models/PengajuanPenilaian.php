<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanPenilaian extends Model
{
    use HasUuids;

    protected $table = 'pengajuan_penilaian';

    protected $fillable = [
        'pegawai_id',
        'subindikator_id',
        'instrumen_id',
        'bukti_dukung',
        'original_filename',
        'file_size',
        'file_type',
        'status',
        'tanggal_sk',
        'catatan',
        'catatan_admin',
    ];

    protected $casts = [
        'tanggal_sk' => 'date',
    ];

    /**
     * Get the pegawai that owns this pengajuan penilaian.
     */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    /**
     * Get the subindikator that owns this pengajuan penilaian.
     */
    public function subindikator(): BelongsTo
    {
        return $this->belongsTo(SubIndikator::class);
    }

    /**
     * Get the instrumen that owns this pengajuan penilaian.
     */
    public function instrumen(): BelongsTo
    {
        return $this->belongsTo(Instrumen::class);
    }
}
