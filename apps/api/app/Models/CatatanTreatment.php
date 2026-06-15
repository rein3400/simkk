<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatatanTreatment extends Model
{
    use SoftDeletes;

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
