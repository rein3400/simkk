<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $table = 'booking';

    protected $fillable = [
        'pasien_id',
        'terapis_id',
        'layanan_id',
        'scheduled_at',
        'duration_min',
        'status',
        'notes',
        'source',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'duration_min' => 'integer',
    ];

    public function pasien(): BelongsTo
    {
        return $this->belongsTo(Pasien::class);
    }

    public function terapis(): BelongsTo
    {
        return $this->belongsTo(Terapis::class);
    }

    public function layanan(): BelongsTo
    {
        return $this->belongsTo(Layanan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function endsAt(): \Carbon\CarbonImmutable
    {
        return \Carbon\CarbonImmutable::parse($this->scheduled_at)
            ->addMinutes((int) $this->duration_min);
    }
}
