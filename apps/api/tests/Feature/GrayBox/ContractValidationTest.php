<?php

namespace Tests\Feature\GrayBox;

use App\Models\BatchStok;
use App\Models\Layanan;
use App\Models\Pasien;
use App\Models\PembelianSupplier;
use App\Models\Produk;
use App\Models\Terapis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GB-CONTRACT: API contract validation.
 *
 * Cross-checks every documented endpoint's response shape against what
 * apps/web/src/services/api.ts and src/types/domain.ts expect. Tests are
 * gray-box: we read source to know expected keys, then assert.
 */
class ContractValidationTest extends TestCase
{
    use RefreshDatabase;

    private function seedFullData(): array
    {
        $kasir = User::create(['username' => 'kasir', 'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Nadia', 'level' => 'Kasir', 'shift' => 'Pagi']);
        $terapis = Terapis::create(['nama' => 'Sinta', 'spesialisasi' => 'Acne', 'status' => 'Tersedia']);
        $layanan = Layanan::create(['nama' => 'Facial Acne', 'kategori' => 'Treatment', 'durasi' => '55m', 'harga' => 285000, 'komisi_rate' => 0.12]);
        $produk = Produk::create(['nama' => 'Cleanser', 'kategori' => 'Skincare', 'stok_min' => 5]);
        BatchStok::create(['produk_id' => $produk->id, 'kode_batch' => 'B-001', 'qty' => 50, 'hpp' => 20000, 'kadaluarsa' => '2026-12-31', 'supplier' => 'PT X']);
        $pasien = Pasien::create(['nama_pasien' => 'Test User', 'usia' => 25, 'alamat' => 'Jl. Test', 'nomor_telp' => '0812', 'rekam_medis_id' => 'RM-001', 'keluhan' => 'Acne']);

        return compact('kasir', 'terapis', 'layanan', 'produk', 'pasien');
    }

    /** GB-CONTRACT-01: POST /api/login response shape matches LoginResult interface. */
    public function test_login_response_matches_frontend_type(): void
    {
        User::create(['username' => 'kasir', 'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Nadia', 'level' => 'Kasir', 'shift' => 'Pagi']);

        $res = $this->postJson('/api/login', [
            'username' => 'kasir',
            'password' => 'simkk-2026',
            'level'    => 'Kasir',
        ])->assertOk()->json();

        $this->assertArrayHasKey('token', $res);
        $this->assertIsString($res['token']);
        $this->assertIsArray($res['user']);
        foreach (['id', 'username', 'nama_lengkap', 'level', 'shift'] as $k) {
            $this->assertArrayHasKey($k, $res['user'], "Login user missing key: $k");
        }
    }

    /** GB-CONTRACT-02: GET /api/bootstrap keys match AppData interface. */
    public function test_bootstrap_response_matches_frontend_appdata(): void
    {
        $d = $this->seedFullData();
        Sanctum::actingAs($d['kasir']);

        $res = $this->getJson('/api/bootstrap')->assertOk()->json();

        foreach (['users', 'patients', 'services', 'therapists', 'transactions', 'inventory', 'reports'] as $k) {
            $this->assertArrayHasKey($k, $res, "Bootstrap missing top-level key: $k");
            $this->assertIsArray($res[$k]);
        }

        // Patient shape (camelCase as expected by FE)
        $p = $res['patients'][0];
        foreach (['id', 'name', 'age', 'phone', 'recordId', 'concern', 'treatments', 'photos'] as $k) {
            $this->assertArrayHasKey($k, $p, "Patient missing key: $k");
        }

        // Service shape
        $s = $res['services'][0];
        foreach (['id', 'name', 'category', 'duration', 'price', 'commissionRate', 'stockProductId', 'stockImpact'] as $k) {
            $this->assertArrayHasKey($k, $s, "Service missing key: $k");
        }

        // Therapist shape
        $t = $res['therapists'][0];
        foreach (['id', 'name', 'specialty', 'status'] as $k) {
            $this->assertArrayHasKey($k, $t, "Therapist missing key: $k");
        }
    }

    /** GB-CONTRACT-03: POST /api/transactions/pay response shape. */
    public function test_pay_response_shape(): void
    {
        $d = $this->seedFullData();
        Sanctum::actingAs($d['kasir']);

        $res = $this->postJson('/api/transactions/pay', [
            'pasien_id'  => $d['pasien']->id,
            'terapis_id' => $d['terapis']->id,
            'items'      => [['serviceId' => $d['layanan']->id, 'qty' => 1]],
        ])->assertCreated()->json();

        $this->assertArrayHasKey('transaction', $res);
        $this->assertArrayHasKey('receipt', $res);
        $this->assertArrayHasKey('cashLedger', $res);

        $tx = $res['transaction'];
        foreach (['id', 'patient', 'therapist', 'status', 'subtotal', 'discount', 'paymentMethod', 'total', 'commission', 'time'] as $k) {
            $this->assertArrayHasKey($k, $tx, "Transaction missing key: $k");
        }

        $rc = $res['receipt'];
        foreach (['id', 'transactionId', 'subtotal', 'discount', 'paymentMethod', 'total'] as $k) {
            $this->assertArrayHasKey($k, $rc, "Receipt missing key: $k");
        }

        $cl = $res['cashLedger'];
        foreach (['type', 'amount', 'transactionId'] as $k) {
            $this->assertArrayHasKey($k, $cl, "CashLedger missing key: $k");
        }
    }

    /** GB-CONTRACT-04: POST /api/patients/{id}/treatments response shape. */
    public function test_add_treatment_response_shape(): void
    {
        $d = $this->seedFullData();
        $terapisUser = User::create(['username' => 'terapis', 'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Sinta', 'level' => 'Terapis', 'shift' => 'Pagi']);
        // Assign the patient to this Terapis (F-007 requires it)
        $d['pasien']->assigned_terapis_id = $d['terapis']->id;
        $d['pasien']->save();

        Sanctum::actingAs($terapisUser);

        $res = $this->postJson("/api/patients/{$d['pasien']->id}/treatments", [
            'judul'   => 'Test treatment',
            'catatan' => 'Test notes',
        ])->assertCreated()->json();

        foreach (['id', 'date', 'therapist', 'title', 'notes'] as $k) {
            $this->assertArrayHasKey($k, $res, "Treatment response missing key: $k");
        }
    }

    /** GB-CONTRACT-05: POST /api/patients/{id}/photos response shape. */
    public function test_add_photo_response_shape(): void
    {
        $d = $this->seedFullData();
        $terapisUser = User::create(['username' => 'terapis2', 'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Sinta', 'level' => 'Terapis', 'shift' => 'Pagi']);
        $d['pasien']->assigned_terapis_id = $d['terapis']->id;
        $d['pasien']->save();

        Sanctum::actingAs($terapisUser);

        // Real 2x2 PNG generated by GD, base64-encoded as data URL (the FE's actual format)
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgQAYAAA4AAbGa6gYAAAAASUVORK5CYII=';

        $res = $this->postJson("/api/patients/{$d['pasien']->id}/photos", [
            'label'    => 'Before',
            'filename' => 'test.png',
            'content'  => $png,
        ])->assertCreated()->json();

        foreach (['id', 'label', 'date', 'objectRef'] as $k) {
            $this->assertArrayHasKey($k, $res, "Photo response missing key: $k");
        }

        // id is a string per the FE type
        $this->assertIsString($res['id']);
        $this->assertStringStartsWith('clinical/', $res['objectRef']);
    }

    /** GB-CONTRACT-06: POST /api/inventory/purchases response shape. */
    public function test_add_purchase_response_shape(): void
    {
        $d = $this->seedFullData();
        $gudangUser = User::create(['username' => 'gudang', 'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Budi', 'level' => 'Gudang', 'shift' => 'Pagi']);
        Sanctum::actingAs($gudangUser);

        $res = $this->postJson('/api/inventory/purchases', [
            'produk_id'  => $d['produk']->id,
            'supplier'   => 'PT Y',
            'kode_batch' => 'B-002',
            'qty'        => 30,
            'hpp'        => 25000,
            'kadaluarsa' => '2027-06-30',
        ])->assertCreated()->json();

        foreach (['id', 'name', 'category', 'totalStock', 'newBatch'] as $k) {
            $this->assertArrayHasKey($k, $res, "Purchase response missing key: $k");
        }

        $nb = $res['newBatch'];
        foreach (['code', 'qty', 'hpp', 'expiry', 'supplier'] as $k) {
            $this->assertArrayHasKey($k, $nb, "newBatch missing key: $k");
        }
    }
}
