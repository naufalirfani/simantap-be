<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LampiranAsesmen extends Model
{
    use HasUuids;

    protected $table = 'lampiran_asesmen';

    protected $fillable = [
        'pegawai_id',
        'nama_asesmen',
        'file_path',
        'original_filename',
        'file_size',
        'file_type',
    ];

    /**
     * Get the pegawai that owns this lampiran asesmen.
     */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }
}
