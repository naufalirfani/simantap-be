<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Indikator extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'indikators';

    protected $fillable = [
        'indikator',
        'bobot',
        'penilaian',
    ];

    protected $casts = [
        'bobot' => 'decimal:2',
    ];

    public function subIndikators()
    {
        return $this->hasMany(SubIndikator::class, 'indikator_id');
    }
}
