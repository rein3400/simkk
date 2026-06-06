<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchStok extends Model
{
    protected $table = 'batch_stok';

    protected $fillable = [
        'produk_id',
        'kode_batch',
        'qty',
        'hpp',
        'kadaluarsa',
        'supplier',
    ];

    protected $casts = [
        'kadaluarsa' => 'date',
    ];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }
}
