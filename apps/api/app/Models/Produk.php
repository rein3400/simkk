<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produk extends Model
{
    protected $table = 'produk';

    protected $fillable = [
        'nama',
        'kategori',
    ];

    public function batches(): HasMany
    {
        return $this->hasMany(BatchStok::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PembelianSupplier::class);
    }
}
