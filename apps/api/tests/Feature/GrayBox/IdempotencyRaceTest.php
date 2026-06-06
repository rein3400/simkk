<?php

namespace Tests\Feature\GrayBox;

use App\Models\BatchStok;
use App\Models\IdempotencyKey;
use App\Models\Layanan;
use App\Models\Pasien;
use App\Models\Produk;
use App\Models\Terapis;
use App\Models\Transaksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GB-RACE: idempotency + race conditions.
 *
 *  - Idempotency-Key header on /pay: optional, but when present must replay.
 *  - Two parallel /pay with same key: only one creates a transaction.
 *  - Race on last stock item: only one succeeds, other gets 422.
 */
class IdempotencyRaceTest extends TestCase
{
    use RefreshDatabase;

    private function seedStock(int $stock = 1): array
    {
        $kasir = User::create(['username' => 'kasir', 'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Nadia', 'level' => 'Kasir', 'shift' => 'Pagi']);
        $terapis = Terapis::create(['nama' => 'Sinta', 'spesialisasi' => 'Acne', 'status' => 'Tersedia']);
        $pasien = Pasien::create(['nama_pasien' => 'Test', 'usia' => 25, 'alamat' => 'Jl. X', 'nomor_telp' => '0812', 'rekam_medis_id' => 'RM-TEST-001']);
        $produk = Produk::create(['nama' => 'Cream', 'kategori' => 'Skincare', 'stok_min' => 5]);
        $layanan = Layanan::create(['nama' => 'Facial', 'kategori' => 'Treatment', 'durasi' => '55m', 'harga' => 285000, 'komisi_rate' => 0.12, 'stok_produk_id' => $produk->id, 'dampak_stok' => 'Decrement']);
        BatchStok::create(['produk_id' => $produk->id, 'kode_batch' => 'B-001', 'qty' => $stock, 'hpp' => 20000, 'kadaluarsa' => '2026-12-31', 'supplier' => 'PT X']);

        return compact('kasir', 'terapis', 'pasien', 'produk', 'layanan');
    }

    private function payload(array $d): array
    {
        return [
            'pasien_id'  => $d['pasien']->id,
            'terapis_id' => $d['terapis']->id,
            'items'      => [['serviceId' => $d['layanan']->id, 'qty' => 1]],
        ];
    }

    /** GB-RACE-01: Idempotency-Key header is OPTIONAL on /pay (not required). */
    public function test_pay_works_without_idempotency_key(): void
    {
        $d = $this->seedStock();
        Sanctum::actingAs($d['kasir']);

        $this->postJson('/api/transactions/pay', $this->payload($d))
            ->assertCreated();
    }

    /** GB-RACE-02: same key twice -> same response, only one transaksi. */
    public function test_same_idempotency_key_returns_cached_response(): void
    {
        $d = $this->seedStock(10);
        Sanctum::actingAs($d['kasir']);

        $key = 'idem-key-12345678';
        $first = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/transactions/pay', $this->payload($d))
            ->assertCreated()
            ->json();

        $this->assertEquals(1, Transaksi::count(), 'first call should create one transaksi');

        $second = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/transactions/pay', $this->payload($d))
            ->assertStatus(201)
            ->json();

        $this->assertEquals(1, Transaksi::count(), 'replay should NOT create a duplicate');
        $this->assertEquals($first['transaction']['id'], $second['transaction']['id']);
    }

    /** GB-RACE-03: invalid Idempotency-Key format -> 422. */
    public function test_malformed_idempotency_key_rejected(): void
    {
        $d = $this->seedStock();
        Sanctum::actingAs($d['kasir']);

        // 7 chars (below min 8)
        $this->withHeaders(['Idempotency-Key' => 'short77'])
            ->postJson('/api/transactions/pay', $this->payload($d))
            ->assertStatus(422);

        // Contains invalid char
        $this->withHeaders(['Idempotency-Key' => 'bad/key@12345678'])
            ->postJson('/api/transactions/pay', $this->payload($d))
            ->assertStatus(422);
    }

    /** GB-RACE-04: idempotency_keys row is persisted on success. */
    public function test_idempotency_key_persisted(): void
    {
        $d = $this->seedStock();
        Sanctum::actingAs($d['kasir']);

        $this->withHeaders(['Idempotency-Key' => 'persist-key-12345678'])
            ->postJson('/api/transactions/pay', $this->payload($d))
            ->assertCreated();

        $this->assertEquals(1, IdempotencyKey::count());
        $row = IdempotencyKey::first();
        $this->assertEquals(201, $row->status_code);
        $this->assertEquals('transactions.pay', $row->endpoint);
        $this->assertEquals(64, strlen($row->key_hash)); // sha256 hex
    }

    /** GB-RACE-05: race on last stock item.
     *
     *  In a single-threaded test environment, the lockForUpdate() in
     *  decrementStock() serializes access. We simulate the race by
     *  calling /pay twice in sequence with stock=1: first succeeds, second 422.
     */
    public function test_last_stock_item_race_serializes(): void
    {
        $d = $this->seedStock(1);
        Sanctum::actingAs($d['kasir']);

        $this->postJson('/api/transactions/pay', $this->payload($d))
            ->assertCreated();

        $this->postJson('/api/transactions/pay', $this->payload($d))
            ->assertStatus(422);
    }

    /** GB-RACE-06: two concurrent /pay with stock=2 + qty=2 each -> one wins, one loses.
     *  The first call consumes the only batch; the second must fail with 422. */
    public function test_two_pay_calls_both_qty2_with_stock2(): void
    {
        $d = $this->seedStock(2);
        Sanctum::actingAs($d['kasir']);

        $payload = [
            'pasien_id'  => $d['pasien']->id,
            'terapis_id' => $d['terapis']->id,
            'items'      => [['serviceId' => $d['layanan']->id, 'qty' => 2]],
        ];

        $r1 = $this->postJson('/api/transactions/pay', $payload);
        $r2 = $this->postJson('/api/transactions/pay', $payload);

        $statuses = [$r1->status(), $r2->status()];
        sort($statuses);
        $this->assertEquals([201, 422], $statuses, 'exactly one call must succeed');
    }

    /** GB-RACE-07: FIFO ordering preserved by query. */
    public function test_fifo_ordering(): void
    {
        $d = $this->seedStock(0);
        // Add three batches with different expiries
        BatchStok::create(['produk_id' => $d['produk']->id, 'kode_batch' => 'B-2027', 'qty' => 5, 'hpp' => 20000, 'kadaluarsa' => '2027-06-30', 'supplier' => 'PT A']);
        BatchStok::create(['produk_id' => $d['produk']->id, 'kode_batch' => 'B-2026', 'qty' => 5, 'hpp' => 20000, 'kadaluarsa' => '2026-09-15', 'supplier' => 'PT B']);
        BatchStok::create(['produk_id' => $d['produk']->id, 'kode_batch' => 'B-REUS', 'qty' => 5, 'hpp' => 20000, 'kadaluarsa' => null, 'supplier' => 'PT C']);

        Sanctum::actingAs($d['kasir']);

        // qty 7 should consume earliest 5 (B-2026) + 2 of B-2027; B-REUS untouched.
        $this->postJson('/api/transactions/pay', [
            'pasien_id'  => $d['pasien']->id,
            'terapis_id' => $d['terapis']->id,
            'items'      => [['serviceId' => $d['layanan']->id, 'qty' => 7]],
        ])->assertCreated();

        $this->assertEquals(0, BatchStok::where('kode_batch', 'B-2026')->value('qty'));
        $this->assertEquals(3, BatchStok::where('kode_batch', 'B-2027')->value('qty'));
        $this->assertEquals(5, BatchStok::where('kode_batch', 'B-REUS')->value('qty'));
    }
}
