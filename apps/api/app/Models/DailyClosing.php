<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyClosing extends Model
{
    protected $table = 'daily_closing';

    protected $fillable = [
        'tanggal',
        'user_kasir_id',
        'submitted_at',
        'user_manajer_id',
        'approved_at',
        'status',
        'total_penjualan',
        'total_card',
        'total_tunai',
        'pnl',
        'setoran_bank',
        'signature_kasir_path',
        'signature_manajer_path',
        'pdf_path',
        'catatan',
    ];

    protected $casts = [
        'tanggal'             => 'date',
        'submitted_at'        => 'datetime',
        'approved_at'         => 'datetime',
        'total_penjualan'     => 'integer',
        'total_card'          => 'integer',
        'total_tunai'         => 'integer',
        'pnl'                 => 'integer',
        'setoran_bank'        => 'integer',
    ];

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_SUBMITTED= 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FINAL    = 'final';

    public function kasir(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_kasir_id');
    }

    public function manajer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_manajer_id');
    }
}
