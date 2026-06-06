<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaksi extends Model
{
    protected $table = 'transaksi';
    // F-004 fix: surrogate auto-increment `id` is the primary key. `id_transaksi`
    // is the business identifier (unique, not PK), generated from `id` after insert.
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'id_transaksi',
        'pasien_id',
        'terapis_id',
        'status',
        'subtotal',
        'diskon',
        'metode_bayar',
        'total',
        'komisi_total',
        'waktu',
    ];

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class);
    }

    public function terapis(): BelongsTo
    {
        return $this->belongsTo(Terapis::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(TransaksiDetail::class, 'id_transaksi', 'id_transaksi');
    }

    public function bukuKas(): HasMany
    {
        return $this->hasMany(BukuKas::class, 'id_transaksi', 'id_transaksi');
    }
}
