<?php

namespace Database\Seeders;

use App\Models\BatchStok;
use App\Models\PembelianSupplier;
use Illuminate\Database\Seeder;

class BatchStokSeeder extends Seeder
{
    public function run(): void
    {
        $batches = [
            ['produk_id' => 1, 'kode_batch' => 'BS-0426-A', 'qty' => 12, 'hpp' => 98000,  'kadaluarsa' => '2026-09-12', 'supplier' => 'PT Dermalab'],
            ['produk_id' => 1, 'kode_batch' => 'BS-0526-B', 'qty' => 22, 'hpp' => 102000, 'kadaluarsa' => '2027-01-20', 'supplier' => 'PT Dermalab'],
            ['produk_id' => 2, 'kode_batch' => 'DS-1225-X', 'qty' => 5,  'hpp' => 72000,  'kadaluarsa' => '2026-06-30', 'supplier' => 'CV Sunmed'],
            ['produk_id' => 2, 'kode_batch' => 'DS-0526-Y', 'qty' => 13, 'hpp' => 75000,  'kadaluarsa' => '2027-02-12', 'supplier' => 'CV Sunmed'],
            ['produk_id' => 3, 'kode_batch' => 'CT-0326-C', 'qty' => 9,  'hpp' => 61000,  'kadaluarsa' => '2026-11-01', 'supplier' => 'Beauty Core'],
            ['produk_id' => 4, 'kode_batch' => 'PM-0226-A', 'qty' => 6,  'hpp' => 135000, 'kadaluarsa' => '2026-08-01', 'supplier' => 'Aesthetic Pro'],
            ['produk_id' => 4, 'kode_batch' => 'PM-0526-B', 'qty' => 8,  'hpp' => 136500, 'kadaluarsa' => '2027-03-15', 'supplier' => 'Aesthetic Pro'],
            ['produk_id' => 5, 'kode_batch' => 'LED-0126',  'qty' => 42, 'hpp' => 21000,  'kadaluarsa' => null,         'supplier' => 'Medlite'],
            ['produk_id' => 6, 'kode_batch' => 'HA-0426',   'qty' => 11, 'hpp' => 88000,  'kadaluarsa' => '2026-10-22', 'supplier' => 'Beauty Core'],
            ['produk_id' => 7, 'kode_batch' => 'AM-0526',   'qty' => 57, 'hpp' => 18500,  'kadaluarsa' => '2027-02-18', 'supplier' => 'PT Dermalab'],
            ['produk_id' => 8, 'kode_batch' => 'SG-0526',   'qty' => 120,'hpp' => 2500,   'kadaluarsa' => '2028-05-01', 'supplier' => 'Medlite'],
        ];

        foreach ($batches as $b) {
            BatchStok::create($b);
            PembelianSupplier::create($b);
        }
    }
}
