<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatatanTreatment extends Model
{
    protected $table = 'catatan_treatment';

    protected $fillable = [
        'pasien_id',
        'tanggal',
        'terapis',
        'judul',
        'catatan',
    ];

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class);
    }
}
