<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\JenisJabatan;
use App\Models\SubIndikator;

class StandarKompetensiMsk extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'standar_kompetensi_msk';

    protected $fillable = [
        'jenis_jabatan_id',
        'subindikator_id',
        'standar',
    ];

    public function jenisJabatan()
    {
        return $this->belongsTo(JenisJabatan::class, 'jenis_jabatan_id');
    }

    public function subindikator()
    {
        return $this->belongsTo(SubIndikator::class, 'subindikator_id');
    }
}
