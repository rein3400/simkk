<?php

namespace Tests\Feature;

use App\Models\Layanan;
use App\Models\Pasien;
use App\Models\Terapis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransaksiTest extends TestCase
{
    use RefreshDatabase;

    private function seedBaseData(): array
    {
        $kasir = User::create(['username' => 'kasir', 'password' => bcrypt('simkk-2026'), 'nama_lengkap' => 'Nadia', 'level' => 'Kasir', 'shift' => 'Pagi']);
        $pasien = Pasien::create(['nama_pasien' => 'Test', 'usia' => 25, 'alamat' => 'Jl. X', 'nomor_telp' => '0812', 'rekam_medis_id' => 'RM-TEST-001']);
        $terapis = Terapis::create(['nama' => 'Sinta', 'spesialisasi' => 'Acne', 'status' => 'Tersedia']);
        Layanan::create(['nama' => 'Facial', 'kategori' => 'Treatment', 'durasi' => '55m', 'harga' => 285000, 'komisi_rate' => 0.12]);
        return compact('kasir', 'pasien', 'terapis');
    }

    public function test_pay_creates_transaction_and_cash_ledger(): void
    {
        $d = $this->seedBaseData();

        $this->actingAs($d['kasir'])
            ->postJson('/api/transactions/pay', [
                'pasien_id'  => $d['pasien']->id,
                'terapis_id' => $d['terapis']->id,
                'items'      => [['serviceId' => 1, 'qty' => 1]],
            ])->assertCreated()
            ->assertJsonStructure([
                'transaction' => ['id', 'patient', 'therapist', 'status', 'total', 'commission'],
                'receipt',
                'cashLedger',
            ]);
    }

    public function test_pay_calculates_komisi(): void
    {
        $d = $this->seedBaseData();

        $res = $this->actingAs($d['kasir'])
            ->postJson('/api/transactions/pay', [
                'pasien_id'  => $d['pasien']->id,
                'terapis_id' => $d['terapis']->id,
                'items'      => [['serviceId' => 1, 'qty' => 1]],
            ])->json();

        // 285000 * 0.12 = 34200
        $this->assertEquals(34200, $res['transaction']['commission']);
        $this->assertEquals('Lunas', $res['transaction']['status']);
    }

    public function test_pay_applies_discount(): void
    {
        $d = $this->seedBaseData();

        $res = $this->actingAs($d['kasir'])
            ->postJson('/api/transactions/pay', [
                'pasien_id'  => $d['pasien']->id,
                'terapis_id' => $d['terapis']->id,
                'items'      => [['serviceId' => 1, 'qty' => 1]],
                'diskon'     => 50000,
            ])->json();

        $this->assertEquals(235000, $res['transaction']['total']);
    }
}
