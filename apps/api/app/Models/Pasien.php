<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pasien extends Model
{
    protected $table = 'pasien';

    protected $fillable = [
        'nama_pasien',
        'usia',
        'alamat',
        'nomor_telp',
        'rekam_medis_id',
        'keluhan',
        'last_visit',
        'risk_note',
    ];

    public function treatments(): HasMany
    {
        return $this->hasMany(CatatanTreatment::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(FotoKlinis::class);
    }

    public function transaksis(): HasMany
    {
        return $this->hasMany(Transaksi::class);
    }
}
