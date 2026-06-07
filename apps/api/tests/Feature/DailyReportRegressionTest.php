<?php

namespace Tests\Feature;

use App\Models\Layanan;
use App\Models\Pasien;
use App\Models\Produk;
use App\Models\Terapis;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * RPT-01 regression: GET /api/daily-reports/{tanggal}/export must not throw
 * "Call to undefined relationship [produk]" when transaksi_detail rows are
 * backed by the Layanan model (TransaksiDetail::layanan(), not ->produk()).
 *
 * The original failure surfaced as HTTP 409 with the missing-relation message
 * inside DailyReportService::buildPayload() eager-load.
 */
class DailyReportRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-06-07';

    private function manajer(): User
    {
        return User::create([
            'username'     => 'mgr_rpt01',
            'password'     => bcrypt('x'),
            'nama_lengkap' => 'Hendra Wijaya',
            'level'        => 'Manajer',
            'shift'        => 'Pagi',
        ]);
    }

    private function seedLunasTransactionWithLayananDetail(int $total, string $metode = 'Tunai'): Transaksi
    {
        $pasien  = Pasien::create([
            'nama_pasien'     => 'RPT01 Patient',
            'usia'            => 27,
            'alamat'          => 'Jl. Test No.1',
            'nomor_telp'      => '081200000001',
            'rekam_medis_id'  => 'RM-' . uniqid('', true),
        ]);
        $terapis = Terapis::create(['nama' => 'RPT01 Terapis', 'spesialisasi' => 'Facial', 'status' => 'Tersedia']);

        // Layanan needs a related Produk (nullable FK but the column exists).
        $produk = Produk::create(['nama' => 'RPT01 Serum', 'kategori' => 'Skincare']);
        $layanan = Layanan::create([
            'nama'           => 'RPT01 Facial Brightening',
            'kategori'       => 'Treatment',
            'durasi'         => '60 menit',
            'harga'          => $total,
            'komisi_rate'    => 0.10,
            'stok_produk_id' => $produk->id,
            'dampak_stok'    => null,
        ]);

        $trx = new Transaksi();
        $trx->timestamps = false;
        $trx->forceFill([
            'id_transaksi' => 'TRX-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'pasien_id'    => $pasien->id,
            'terapis_id'   => $terapis->id,
            'status'       => 'Lunas',
            'subtotal'     => $total,
            'diskon'       => 0,
            'metode_bayar' => $metode,
            'total'        => $total,
            'komisi_total' => 0,
            'waktu'        => '10:00',
            'created_at'   => Carbon::parse(self::TANGGAL . ' 10:00:00'),
            'updated_at'   => Carbon::parse(self::TANGGAL . ' 10:00:00'),
        ])->save();

        TransaksiDetail::create([
            'id_transaksi'  => $trx->id_transaksi,
            'id_produk'     => $layanan->id,
            'id_terapis'    => $terapis->id,
            'nilai_komisi'  => 0,
            'qty'           => 1,
            'harga_satuan'  => $total,
        ]);

        return $trx;
    }

    public function test_export_endpoint_does_not_throw_undefined_relationship_produk(): void
    {
        $manajer = $this->manajer();
        $this->seedLunasTransactionWithLayananDetail(250000, 'Tunai');

        // Pre-fix: this returned HTTP 409 with
        //   "Call to undefined relationship [produk] on model [App\Models\TransaksiDetail]"
        $res = $this->actingAs($manajer)
            ->get('/api/daily-reports/' . self::TANGGAL . '/export');

        $res->assertOk();
        $this->assertEquals('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_export_pdf_body_contains_expected_section_headers(): void
    {
        $manajer = $this->manajer();
        $this->seedLunasTransactionWithLayananDetail(300000, 'Tunai');

        $res = $this->actingAs($manajer)
            ->get('/api/daily-reports/' . self::TANGGAL . '/export');

        $res->assertOk();

        // Dompdf compresses the content stream with FlateDecode, so the
        // section headers are not greppable in the raw PDF bytes. Render the
        // same Blade view with the same payload directly: the HTML is the
        // source of truth that gets compressed into the PDF, and a regression
        // in the view propagates to both the rendered HTML and the PDF.
        $payload = (new \App\Services\DailyReportService())->buildPayload(self::TANGGAL);
        $html = \Illuminate\Support\Facades\View::make('reports.daily', $payload + [
            'idr' => fn (int $n) => 'Rp' . number_format($n, 0, ',', '.'),
        ])->render();

        $this->assertStringContainsString('CASH AT CASHIER', $html);
        $this->assertStringContainsString('NET SALES', $html);
        $this->assertStringContainsString('CASH OUT', $html);
        $this->assertStringContainsString('P n L', $html);

        // Also assert the PDF magic header for the controller contract.
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_build_payload_uses_layanan_kategori_not_produk(): void
    {
        $this->seedLunasTransactionWithLayananDetail(175000, 'Tunai');

        $svc = \App\Services\DailyReportService::fromConfig();
        $payload = $svc->buildPayload(self::TANGGAL);

        // The Layanan we created has kategori = 'Treatment' (not 'Layanan' fallback).
        $this->assertSame(175000, $payload['totalSales']);
        $this->assertArrayHasKey('Treatment', $payload['netSalesByCategory']);
        $this->assertSame(175000, $payload['netSalesByCategory']['Treatment']);
        $this->assertSame(0, $payload['netSalesByCategory']['Layanan']);
    }
}
