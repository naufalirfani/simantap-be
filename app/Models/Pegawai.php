<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    use HasUuids;

    protected $table = 'pegawai';

    protected $fillable = [
        'pegawai_id',
        'nip',
        'name',
        'email',
        'unit_organisasi_name',
        'jabatan_name',
        'jenis_jabatan',
        'golongan',
        'json',
        'avatar',
    ];

    protected $casts = [
        'json' => 'array',
    ];

    /**
     * Get lokasi_kerja from json field
     */
    public function getLokasiKerjaAttribute()
    {
        return $this->json['lokasiKerja'] ?? null;
    }
}
