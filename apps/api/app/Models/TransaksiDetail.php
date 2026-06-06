<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaksiDetail extends Model
{
    protected $table = 'transaksi_detail';

    protected $fillable = [
        'id_transaksi',
        'id_produk',
        'id_terapis',
        'nilai_komisi',
        'qty',
        'harga_satuan',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }

    public function layanan(): BelongsTo
    {
        return $this->belongsTo(Layanan::class, 'id_produk');
    }

    public function terapis(): BelongsTo
    {
        return $this->belongsTo(Terapis::class, 'id_terapis');
    }
}
