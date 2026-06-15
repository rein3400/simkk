<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PembelianSupplier extends Model
{
    use SoftDeletes;
    protected $table = 'pembelian_supplier';

    protected $fillable = [
        'produk_id',
        'kode_batch',
        'supplier_id',
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

    public function supplierRef(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
