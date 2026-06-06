<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokMutasi extends Model
{
    protected $table = 'stok_mutasi';

    protected $fillable = [
        'id_produk',
        'tanggal',
        'tipe',
        'arah',
        'qty',
        'id_batch',
        'id_transaksi',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'qty'     => 'decimal:2',
    ];

    public const ARAH_IN  = 'IN';
    public const ARAH_OUT = 'OUT';

    public const TIPE_PEMBELIAN      = 'pembelian';
    public const TIPE_RETURN_PURCHASE= 'return_purchase';
    public const TIPE_RETURN_SALES   = 'return_sales';
    public const TIPE_SALES          = 'sales';
    public const TIPE_BARANG_KELUAR  = 'barang_keluar';

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'id_produk');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchStok::class, 'id_batch');
    }

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi');
    }
}
