<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PetaJabatan extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'peta_jabatan';

    protected $fillable = [
        'id',
        'parent_id',
        'nama_jabatan',
        'slug',
        'unit_kerja',
        'level',
        'order_index',
        'bezetting',
        'kebutuhan_pegawai',
        'is_pusat',
        'jenis_jabatan',
        'jabatan_id',
        'kelas_jabatan',
        'pejabat',
    ];

    protected $casts = [
        'level' => 'integer',
        'order_index' => 'integer',
        'bezetting' => 'integer',
        'kebutuhan_pegawai' => 'integer',
        'is_pusat' => 'boolean',
        'pejabat' => 'array',
    ];

    public $incrementing = false;
    protected $keyType = 'string';
}
