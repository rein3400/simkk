<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Layanan;
use App\Models\Produk;
use App\Models\BatchStok;
use App\Models\PembelianSupplier;

class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('==> Seeding production bootstrap data...');

        $users = [
            ['username' => 'kasir',   'nama_lengkap' => 'Diani Arthantri',  'level' => 'Kasir'],
            ['username' => 'terapis', 'nama_lengkap' => 'Rani Wulandari',   'level' => 'Terapis'],
            ['username' => 'gudang',  'nama_lengkap' => 'Budi Santoso',     'level' => 'Gudang'],
            ['username' => 'manajer', 'nama_lengkap' => 'Hendra Wijaya',    'level' => 'Manajer'],
        ];
        foreach ($users as $u) {
            User::firstOrCreate(
                ['username' => $u['username']],
                [
                    'nama_lengkap' => $u['nama_lengkap'],
                    'password' => Hash::make('simkk-2026'),
                    'level' => $u['level'],
                ]
            );
        }
        $this->command->info('  4 users seeded (default password: simkk-2026)');

        $layanan = [
            ['nama' => 'Facial Basic',       'harga' => 150000, 'durasi' => '45 min', 'kategori' => 'Treatment', 'komisi_rate' => 0.10],
            ['nama' => 'Chemical Peeling',   'harga' => 350000, 'durasi' => '60 min', 'kategori' => 'Treatment', 'komisi_rate' => 0.10],
            ['nama' => 'Microneedling',      'harga' => 500000, 'durasi' => '90 min', 'kategori' => 'Treatment', 'komisi_rate' => 0.10],
            ['nama' => 'Laser Rejuvenation', 'harga' => 800000, 'durasi' => '75 min', 'kategori' => 'Treatment', 'komisi_rate' => 0.10],
            ['nama' => 'Treatment Acne',     'harga' => 250000, 'durasi' => '50 min', 'kategori' => 'Treatment', 'komisi_rate' => 0.10],
        ];
        foreach ($layanan as $l) {
            Layanan::firstOrCreate(['nama' => $l['nama']], $l);
        }
        $this->command->info('  5 layanan seeded');

        $produk = [
            ['nama' => 'Sunscreen SPF 50',      'harga_jual' => 180000, 'satuan' => 'pcs', 'kategori' => 'SUNSCREEN'],
            ['nama' => 'Serum Vitamin C',       'harga_jual' => 250000, 'satuan' => 'pcs', 'kategori' => 'SERUM'],
            ['nama' => 'Moisturizer Hydrating', 'harga_jual' => 200000, 'satuan' => 'pcs', 'kategori' => 'MOISTURIZER'],
            ['nama' => 'Toner Gentle',          'harga_jual' => 150000, 'satuan' => 'pcs', 'kategori' => 'TONER'],
            ['nama' => 'Retinol Night Cream',   'harga_jual' => 320000, 'satuan' => 'pcs', 'kategori' => 'CREAM'],
        ];
        $produkIds = [];
        foreach ($produk as $p) {
            $row = Produk::firstOrCreate(['nama' => $p['nama']], $p);
            $produkIds[] = $row->id;
        }
        $this->command->info('  5 produk seeded');

        $supplier = 'PT Kosmetik Nusantara';
        foreach ($produkIds as $idx => $pid) {
            PembelianSupplier::firstOrCreate(
                ['produk_id' => $pid, 'kode_batch' => "BATCH-{$pid}-OLD"],
                [
                    'qty' => 20,
                    'hpp' => 80000,
                    'supplier' => $supplier,
                    'kadaluarsa' => now()->addMonths(9)->format('Y-m-d'),
                ]
            );
            BatchStok::firstOrCreate(
                ['produk_id' => $pid, 'kode_batch' => "BATCH-{$pid}-OLD"],
                [
                    'qty' => 20,
                    'hpp' => 80000,
                    'kadaluarsa' => now()->addMonths(9)->format('Y-m-d'),
                    'supplier' => $supplier,
                ]
            );
            BatchStok::firstOrCreate(
                ['produk_id' => $pid, 'kode_batch' => "BATCH-{$pid}-NEW"],
                [
                    'qty' => 30,
                    'hpp' => 85000,
                    'kadaluarsa' => now()->addYear()->format('Y-m-d'),
                    'supplier' => $supplier,
                ]
            );
            PembelianSupplier::firstOrCreate(
                ['produk_id' => $pid, 'kode_batch' => "BATCH-{$pid}-NEW"],
                [
                    'qty' => 30,
                    'hpp' => 85000,
                    'supplier' => $supplier,
                    'kadaluarsa' => now()->addYear()->format('Y-m-d'),
                ]
            );
        }
        $this->command->info('  2 batch_stok + 2 pembelian_supplier per produk seeded');

        $this->command->info('==> Production bootstrap complete.');
    }
}
