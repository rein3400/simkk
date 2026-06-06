<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Layanan extends Model
{
    protected $table = 'layanan';

    protected $fillable = [
        'nama',
        'kategori',
        'durasi',
        'harga',
        'komisi_rate',
        'stok_produk_id',
        'dampak_stok',
    ];

    protected $casts = [
        'harga' => 'integer',
        'komisi_rate' => 'decimal:2',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'stok_produk_id');
    }
}
