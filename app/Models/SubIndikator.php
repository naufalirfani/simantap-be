<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubIndikator extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subindikators';

    protected $fillable = [
        'subindikator',
        'bobot',
        'isactive',
        'indikator_id',
    ];

    protected $casts = [
        'bobot' => 'decimal:2',
        'isactive' => 'boolean',
    ];

    public function indikator()
    {
        return $this->belongsTo(Indikator::class, 'indikator_id');
    }

    public function instrumens()
    {
        return $this->hasMany(Instrumen::class, 'subindikator_id');
    }
}
