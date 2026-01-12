<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penilaian extends Model
{
    use HasFactory;

    protected $table = 'penilaians';

    protected $fillable = [
        'pegawai_id',
        'penilaian',
    ];

    protected $casts = [
        'penilaian' => 'array',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }
}
