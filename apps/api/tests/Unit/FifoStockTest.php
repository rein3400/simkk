<?php

namespace Tests\Unit;

use App\Models\BatchStok;
use App\Models\Layanan;
use App\Models\Pasien;
use App\Models\Produk;
use App\Models\Terapis;
use App\Models\User;
use App\Services\TransaksiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FifoStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifo_decrements_earliest_batch_first(): void
    {
        $user = User::create(['username' => 'kasir', 'password' => bcrypt('x'), 'nama_lengkap' => 'X', 'level' => 'Kasir']);
        $pasien = Pasien::create(['nama_pasien' => 'Test', 'usia' => 25, 'alamat' => 'X', 'nomor_telp' => 'X', 'rekam_medis_id' => 'RM-FIFO-001']);
        $terapis = Terapis::create(['nama' => 'T', 'spesialisasi' => 'X', 'status' => 'Tersedia']);
        $produk = Produk::create(['nama' => 'Serum', 'kategori' => 'Skincare']);
        Layanan::create(['nama' => 'Serum Retail', 'kategori' => 'Produk', 'durasi' => 'Retail', 'harga' => 215000, 'komisi_rate' => 0.05, 'stok_produk_id' => $produk->id]);
        // old batch first
        BatchStok::create(['produk_id' => $produk->id, 'kode_batch' => 'OLD', 'qty' => 10, 'hpp' => 90000, 'kadaluarsa' => '2026-08-01', 'supplier' => 'X']);
        BatchStok::create(['produk_id' => $produk->id, 'kode_batch' => 'NEW', 'qty' => 20, 'hpp' => 95000, 'kadaluarsa' => '2027-06-01', 'supplier' => 'X']);

        $service = app(TransaksiService::class);
        $service->pay($pasien->id, $terapis->id, [['serviceId' => 1, 'qty' => 3]]);

        $this->assertEquals(7, (int) BatchStok::where('kode_batch', 'OLD')->first()->qty);
        $this->assertEquals(20, (int) BatchStok::where('kode_batch', 'NEW')->first()->qty);
    }

    public function test_fifo_throws_on_insufficient_stock(): void
    {
        $user = User::create(['username' => 'kasir', 'password' => bcrypt('x'), 'nama_lengkap' => 'X', 'level' => 'Kasir']);
        $pasien = Pasien::create(['nama_pasien' => 'Test', 'usia' => 25, 'alamat' => 'X', 'nomor_telp' => 'X', 'rekam_medis_id' => 'RM-FIFO-002']);
        $terapis = Terapis::create(['nama' => 'T', 'spesialisasi' => 'X', 'status' => 'Tersedia']);
        $produk = Produk::create(['nama' => 'Serum', 'kategori' => 'Skincare']);
        Layanan::create(['nama' => 'Serum Retail', 'kategori' => 'Produk', 'durasi' => 'Retail', 'harga' => 215000, 'komisi_rate' => 0.05, 'stok_produk_id' => $produk->id]);
        BatchStok::create(['produk_id' => $produk->id, 'kode_batch' => 'SMALL', 'qty' => 2, 'hpp' => 90000, 'kadaluarsa' => '2026-08-01', 'supplier' => 'X']);

        $this->expectException(RuntimeException::class);
        app(TransaksiService::class)->pay($pasien->id, $terapis->id, [['serviceId' => 1, 'qty' => 5]]);
    }
}
