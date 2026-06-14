<?php

namespace Tests\Feature;

use App\Models\BatchStok;
use App\Models\BukuKas;
use App\Models\Layanan;
use App\Models\Pasien;
use App\Models\Produk;
use App\Models\Terapis;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class LaporanExportTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::create([
            'username' => 'mgr-export',
            'password' => bcrypt('secret'),
            'nama_lengkap' => 'Hendra Wijaya',
            'level' => 'Manajer',
            'shift' => 'Pagi',
        ]);
    }

    private function seedPaidTransaction(int $total, int $commission): Transaksi
    {
        $patient = Pasien::create([
            'nama_pasien' => 'Pasien Export',
            'usia' => 31,
            'alamat' => 'Jl Export',
            'nomor_telp' => '0812345678',
            'rekam_medis_id' => 'RM-EXPORT',
        ]);
        $therapist = Terapis::create([
            'nama' => 'Sinta Export',
            'spesialisasi' => 'Facial',
            'status' => 'Tersedia',
            'gaji_pokok' => 2500000,
        ]);
        Layanan::create([
            'nama' => 'Facial Export',
            'kategori' => 'Treatment',
            'durasi' => '60 menit',
            'harga' => $total,
            'komisi_rate' => 0.10,
        ]);

        $row = new Transaksi();
        $row->timestamps = false;
        $row->forceFill([
            'id_transaksi' => 'TRX-EXPORT',
            'pasien_id' => $patient->id,
            'terapis_id' => $therapist->id,
            'status' => 'Lunas',
            'subtotal' => $total,
            'diskon' => 0,
            'metode_bayar' => 'Tunai',
            'total' => $total,
            'komisi_total' => $commission,
            'waktu' => '10:00',
            'created_at' => Carbon::parse('2026-06-09 10:00:00'),
            'updated_at' => Carbon::parse('2026-06-09 10:00:00'),
        ])->save();

        return $row;
    }

    private function loadSpreadsheet(string $content): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'simkk-xlsx-');
        $this->assertIsString($path);
        file_put_contents($path, $content);

        try {
            return IOFactory::load($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_finance_export_returns_rendered_pdf_content(): void
    {
        $transaction = $this->seedPaidTransaction(98000, 205200);
        BukuKas::create([
            'id_transaksi' => $transaction->id_transaksi,
            'tipe' => 'Debit',
            'jumlah' => 98000,
            'deskripsi' => 'Pembayaran export',
        ]);

        $response = $this->actingAs($this->manager())
            ->get('/api/reports/finance/export');

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertGreaterThan(2000, strlen($response->getContent()));
    }

    public function test_stock_export_preserves_idr_integer_values(): void
    {
        $product = Produk::create([
            'nama' => 'Barrier Serum',
            'kategori' => 'Serum',
        ]);
        BatchStok::create([
            'produk_id' => $product->id,
            'kode_batch' => 'BS-EXPORT',
            'qty' => 12,
            'hpp' => 98000,
            'kadaluarsa' => '2026-12-31',
            'supplier' => 'Supplier Export',
        ]);

        $response = $this->actingAs($this->manager())
            ->get('/api/reports/stock/export');

        $response->assertOk();
        $sheet = $this->loadSpreadsheet($response->getContent())->getActiveSheet();
        $this->assertEquals(98000, $sheet->getCell('D2')->getValue());
    }

    public function test_commission_export_preserves_idr_integer_values(): void
    {
        $this->seedPaidTransaction(98000, 205200);

        $response = $this->actingAs($this->manager())
            ->get('/api/reports/commission/export');

        $response->assertOk();
        $sheet = $this->loadSpreadsheet($response->getContent())->getActiveSheet();
        $this->assertEquals(205200, $sheet->getCell('D2')->getValue());
        $this->assertEquals(2500000, $sheet->getCell('E2')->getValue());
        $this->assertEquals(2705200, $sheet->getCell('F2')->getValue());
    }
}
