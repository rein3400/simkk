<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $table = 'supplier';

    protected $fillable = [
        'nama',
        'kontak',
        'telepon',
        'email',
        'alamat',
        'catatan',
    ];

    public function pembelianSuppliers(): HasMany
    {
        return $this->hasMany(PembelianSupplier::class, 'supplier_id');
    }
}
