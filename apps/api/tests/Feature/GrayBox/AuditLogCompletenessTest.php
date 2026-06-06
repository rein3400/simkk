<?php

namespace Tests\Feature\GrayBox;

use App\Models\AuditLog;
use App\Models\BatchStok;
use App\Models\Layanan;
use App\Models\Pasien;
use App\Models\Produk;
use App\Models\Terapis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GB-AUDIT: audit log completeness.
 *
 *  - Every write action (login, pay, add_treatment, add_photo, add_purchase)
 *    creates an audit_log entry.
 *  - No UPDATE/DELETE routes for audit_log (immutability at API surface).
 *  - Sensitive data (password) is not stored in data_baru.
 */
class AuditLogCompletenessTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): array
    {
        $kasir = User::create(['username' => 'kasir', 'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Nadia', 'level' => 'Kasir', 'shift' => 'Pagi']);
        $terapis = Terapis::create(['nama' => 'Sinta', 'spesialisasi' => 'Acne', 'status' => 'Tersedia']);
        $produk = Produk::create(['nama' => 'Cream', 'kategori' => 'Skincare', 'stok_min' => 5]);
        $layanan = Layanan::create(['nama' => 'Facial', 'kategori' => 'Treatment', 'durasi' => '55m', 'harga' => 285000, 'komisi_rate' => 0.12, 'stok_produk_id' => $produk->id, 'dampak_stok' => 'Decrement']);
        BatchStok::create(['produk_id' => $produk->id, 'kode_batch' => 'B-001', 'qty' => 10, 'hpp' => 20000, 'kadaluarsa' => '2026-12-31', 'supplier' => 'PT X']);
        $pasien = Pasien::create(['nama_pasien' => 'Test', 'usia' => 25, 'alamat' => 'Jl. X', 'nomor_telp' => '0812', 'rekam_medis_id' => 'RM-TEST-001']);

        return compact('kasir', 'terapis', 'produk', 'layanan', 'pasien');
    }

    /** GB-AUDIT-01: login -> audit entry. */
    public function test_login_audit_entry(): void
    {
        $this->seedAll();

        $this->postJson('/api/login', [
            'username' => 'kasir', 'password' => 'simkk-2026', 'level' => 'Kasir',
        ])->assertOk();

        $entry = AuditLog::where('aksi', 'login')->first();
        $this->assertNotNull($entry);
        $this->assertEquals('user', $entry->entitas);
        $this->assertNotNull($entry->user_id);
        $this->assertNotNull($entry->ip_address);

        // Password must NOT be in the logged data
        $this->assertStringNotContainsString('simkk-2026', json_encode($entry->data_baru ?? []));
        $this->assertStringNotContainsString('password', json_encode($entry->data_baru ?? []));
    }

    /** GB-AUDIT-02: pay -> audit entry with komisi + total. */
    public function test_pay_audit_entry(): void
    {
        $d = $this->seedAll();
        Sanctum::actingAs($d['kasir']);

        $this->postJson('/api/transactions/pay', [
            'pasien_id'  => $d['pasien']->id,
            'terapis_id' => $d['terapis']->id,
            'items'      => [['serviceId' => $d['layanan']->id, 'qty' => 1]],
        ])->assertCreated();

        $entry = AuditLog::where('aksi', 'pay')->first();
        $this->assertNotNull($entry);
        $this->assertEquals('transaksi', $entry->entitas);
        $this->assertNotNull($entry->entitas_id);
        $this->assertNotNull($entry->data_baru);
        $this->assertArrayHasKey('total', $entry->data_baru);
        $this->assertArrayHasKey('komisi', $entry->data_baru);
    }

    /** GB-AUDIT-03: add_purchase -> audit entry.
     *  Note: the current code does NOT call $audit->log on addPurchase. This
     *  is a real gap we want to surface. */
    public function test_add_purchase_audit_entry(): void
    {
        $d = $this->seedAll();
        $gudangUser = User::create(['username' => 'gudang', 'password' => Hash::make('simkk-2026'), 'nama_lengkap' => 'Budi', 'level' => 'Gudang', 'shift' => 'Pagi']);
        Sanctum::actingAs($gudangUser);

        $this->postJson('/api/inventory/purchases', [
            'produk_id'  => $d['produk']->id,
            'supplier'   => 'PT Y',
            'kode_batch' => 'B-002',
            'qty'        => 5,
            'hpp'        => 25000,
            'kadaluarsa' => '2027-06-30',
        ])->assertCreated();

        $entry = AuditLog::where('aksi', 'add_purchase')->first();
        // Source inspection: InventarisService::addPurchase DOES call audit->log
        $this->assertNotNull($entry, 'add_purchase audit entry missing');
        $this->assertEquals('batch_stok', $entry->entitas);
    }

    /** GB-AUDIT-04: api surface has no UPDATE/DELETE route for audit_log. */
    public function test_audit_log_no_update_or_delete_routes(): void
    {
        $routes = collect(Route::getRoutes())->map(fn ($r) => strtoupper(implode('|', $r->methods())) . ' ' . $r->uri());

        $auditWrites = $routes->filter(function ($r) {
            return preg_match('#audit[_/]log#i', $r) &&
                   (str_contains($r, 'PUT') || str_contains($r, 'PATCH') || str_contains($r, 'DELETE'));
        });

        $this->assertCount(0, $auditWrites->all(), 'audit_log must not have any PUT/PATCH/DELETE routes: ' . $auditWrites->implode(', '));
    }

    /** GB-AUDIT-05: audit_log is append-only at the model level — fillable
     *  does not include updated_at mutation, and the controller never edits. */
    public function test_audit_log_model_has_no_update_endpoint_in_codebase(): void
    {
        $controllers = glob(base_path('app/Http/Controllers/**/*.php'));
        $violations = [];
        foreach ($controllers as $c) {
            $content = file_get_contents($c);
            if (preg_match('/AuditLog::(update|destroy|delete)\b/', $content)) {
                $violations[] = $c;
            }
        }
        $this->assertEmpty($violations, 'AuditLog::update/destroy/delete found in: ' . implode(', ', $violations));
    }

    /** GB-AUDIT-06: sensitive data check across all current audit entries. */
    public function test_no_password_in_any_audit_data(): void
    {
        $d = $this->seedAll();
        Sanctum::actingAs($d['kasir']);

        // Trigger several actions
        $this->postJson('/api/login', ['username' => 'kasir', 'password' => 'simkk-2026', 'level' => 'Kasir'])->assertOk();
        $this->postJson('/api/transactions/pay', [
            'pasien_id'  => $d['pasien']->id,
            'terapis_id' => $d['terapis']->id,
            'items'      => [['serviceId' => $d['layanan']->id, 'qty' => 1]],
        ])->assertCreated();

        foreach (AuditLog::all() as $row) {
            $blob = json_encode([
                $row->data_lama, $row->data_baru, $row->aksi, $row->entitas, $row->entitas_id,
            ]);
            $this->assertStringNotContainsString('simkk-2026', $blob, "Audit row {$row->id} leaks password");
            $this->assertStringNotContainsString('$2y$', $blob, "Audit row {$row->id} leaks password hash");
        }
    }
}
