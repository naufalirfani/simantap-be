<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DaftarKotak extends Model
{
    protected $table = 'daftar_kotak';

    protected $fillable = [
        'intervals',
        'kotak',
    ];

    protected $casts = [
        'intervals' => 'array',
        'kotak' => 'array',
    ];
}
