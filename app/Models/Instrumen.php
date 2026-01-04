<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instrumen extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'instrumens';

    protected $fillable = [
        'instrumen',
        'bobot',
        'subindikator_id',
    ];

    protected $casts = [
        'bobot' => 'decimal:2',
    ];

    public function subIndikator()
    {
        return $this->belongsTo(SubIndikator::class, 'subindikator_id');
    }
}
