<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PembelianSupplier extends Model
{
    protected $table = 'pembelian_supplier';

    protected $fillable = [
        'produk_id',
        'kode_batch',
        'qty',
        'hpp',
        'supplier',
        'kadaluarsa',
    ];

    protected $casts = [
        'kadaluarsa' => 'date',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }
}
