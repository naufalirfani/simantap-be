<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Suksesor extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'suksesor';

    protected $fillable = [
        'id',
        'peta_jabatan_id',
        'pegawai_id',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function petaJabatan()
    {
        return $this->belongsTo(PetaJabatan::class, 'peta_jabatan_id');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }
}

