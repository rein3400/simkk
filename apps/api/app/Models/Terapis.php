<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Terapis extends Model
{
    use SoftDeletes;

    protected $table = 'terapis';

    protected $fillable = [
        'nama',
        'spesialisasi',
        'status',
        'gaji_pokok',
    ];

    public function transaksis(): HasMany
    {
        return $this->hasMany(Transaksi::class);
    }
}
