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
    ];

    protected $casts = [
        'syarat' => 'array',
    ];

    public function petaJabatan()
    {
        return $this->belongsTo(PetaJabatan::class, 'jabatan_id');
    }
}
