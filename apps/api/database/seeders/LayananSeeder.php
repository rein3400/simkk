<?php

namespace Database\Seeders;

use App\Models\Layanan;
use Illuminate\Database\Seeder;

class LayananSeeder extends Seeder
{
    public function run(): void
    {
        $layanan = [
            ['nama' => 'Acne Calm Facial',       'kategori' => 'Treatment', 'durasi' => '55m',     'harga' => 285000,  'komisi_rate' => 0.12, 'stok_produk_id' => null, 'dampak_stok' => null],
            ['nama' => 'Bright Peel Mild',       'kategori' => 'Treatment', 'durasi' => '40m',     'harga' => 360000,  'komisi_rate' => 0.14, 'stok_produk_id' => null, 'dampak_stok' => null],
            ['nama' => 'Glow Infusion',          'kategori' => 'Treatment', 'durasi' => '70m',     'harga' => 520000,  'komisi_rate' => 0.16, 'stok_produk_id' => null, 'dampak_stok' => null],
            ['nama' => 'Hydra Cleanse',          'kategori' => 'Treatment', 'durasi' => '45m',     'harga' => 310000,  'komisi_rate' => 0.12, 'stok_produk_id' => null, 'dampak_stok' => null],
            ['nama' => 'LED Recovery',           'kategori' => 'Treatment', 'durasi' => '25m',     'harga' => 180000,  'komisi_rate' => 0.10, 'stok_produk_id' => null, 'dampak_stok' => null],
            ['nama' => 'Barrier Serum',          'kategori' => 'Produk',    'durasi' => 'Retail',  'harga' => 215000,  'komisi_rate' => 0.05, 'stok_produk_id' => 1,    'dampak_stok' => '-1 botol'],
            ['nama' => 'Daily Sunscreen SPF50',  'kategori' => 'Produk',    'durasi' => 'Retail',  'harga' => 175000,  'komisi_rate' => 0.04, 'stok_produk_id' => 2,    'dampak_stok' => '-1 tube'],
            ['nama' => 'Calming Toner',          'kategori' => 'Produk',    'durasi' => 'Retail',  'harga' => 145000,  'komisi_rate' => 0.04, 'stok_produk_id' => 3,    'dampak_stok' => '-1 botol'],
            ['nama' => 'Paket Acne 3x',          'kategori' => 'Paket',     'durasi' => '3 sesi',  'harga' => 760000,  'komisi_rate' => 0.11, 'stok_produk_id' => null, 'dampak_stok' => null],
            ['nama' => 'Paket Bridal Glow',      'kategori' => 'Paket',     'durasi' => '4 sesi',  'harga' => 1450000, 'komisi_rate' => 0.13, 'stok_produk_id' => null, 'dampak_stok' => null],
        ];

        foreach ($layanan as $l) {
            Layanan::create($l);
        }
    }
}
