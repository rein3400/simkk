<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BukuKas extends Model
{
    use SoftDeletes;
    protected $table = 'buku_kas';

    protected $fillable = [
        'id_transaksi',
        'tipe',
        'jumlah',
        'deskripsi',
    ];

    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class, 'id_transaksi', 'id_transaksi');
    }
}
