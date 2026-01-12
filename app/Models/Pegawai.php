<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\PetaJabatan;
use App\Models\JenisJabatan;
use App\Models\Penilaian;

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
        'jenis_jabatan_id',
        'peta_jabatan_id',
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

    public function petaJabatan()
    {
        return $this->belongsTo(PetaJabatan::class, 'peta_jabatan_id', 'id');
    }

    public function jenisJabatan()
    {
        return $this->belongsTo(JenisJabatan::class, 'jenis_jabatan_id', 'id');
    }

    public function penilaian()
    {
        return $this->hasOne(Penilaian::class, 'pegawai_id', 'id');
    }
}
