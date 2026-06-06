<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FotoKlinis extends Model
{
    protected $table = 'foto_klinis';

    protected $fillable = [
        'pasien_id',
        'label',
        'tanggal',
        'object_ref',
    ];

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class);
    }
}
