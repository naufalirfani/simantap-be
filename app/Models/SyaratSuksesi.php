<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyaratSuksesi extends Model
{
    use HasFactory;

    protected $table = 'syarat_suksesi';

    protected $fillable = [
        'jabatan_id',
        'syarat',
        'gunakan_kompetensi_teknis',
        'sesuai_rumpun_jabatan',
        'minimal_usia',
        'maksimal_usia',
        'pangkat_golongan',
    ];

    protected $casts = [
        'syarat' => 'array',
        'gunakan_kompetensi_teknis' => 'boolean',
        'sesuai_rumpun_jabatan' => 'boolean',
        'minimal_usia' => 'integer',
        'maksimal_usia' => 'integer',
        'pangkat_golongan' => 'string',
    ];

    public function petaJabatan()
    {
        return $this->belongsTo(PetaJabatan::class, 'jabatan_id');
    }
}
