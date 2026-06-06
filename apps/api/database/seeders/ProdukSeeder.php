<?php

namespace Database\Seeders;

use App\Models\Produk;
use Illuminate\Database\Seeder;

class ProdukSeeder extends Seeder
{
    public function run(): void
    {
        $produk = [
            ['nama' => 'Barrier Serum',           'kategori' => 'Skincare'],
            ['nama' => 'Daily Sunscreen SPF50',   'kategori' => 'Skincare'],
            ['nama' => 'Calming Toner',           'kategori' => 'Skincare'],
            ['nama' => 'Peeling Solution Mild',   'kategori' => 'Treatment'],
            ['nama' => 'LED Eye Shield',          'kategori' => 'Alat'],
            ['nama' => 'Hydra Ampoule',           'kategori' => 'Treatment'],
            ['nama' => 'Acne Mask Sachet',        'kategori' => 'Treatment'],
            ['nama' => 'Sterile Gauze',           'kategori' => 'Consumable'],
        ];

        foreach ($produk as $p) {
            Produk::create($p);
        }
    }
}
