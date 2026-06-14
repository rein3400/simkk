<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produk extends Model
{
    use SoftDeletes;
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
