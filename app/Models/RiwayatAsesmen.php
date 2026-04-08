<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RiwayatAsesmen extends Model
{
    use HasUuids;

    protected $table = 'riwayat_asesmen';

    protected $keyType = 'string';

    public $incrementing = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'nama_asesmen',
        'pegawai_id',
        'data_asesmen',
    ];

    protected $casts = [
        'data_asesmen' => 'array',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id', 'id');
    }
}
