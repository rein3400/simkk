<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyCashFloat extends Model
{
    protected $table = 'daily_cash_float';

    protected $fillable = [
        'user_id',
        'tanggal',
        'modal_awal',
        'catatan',
    ];

    protected $casts = [
        'tanggal'   => 'date',
        'modal_awal'=> 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
