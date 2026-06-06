<?php

namespace Tests\Feature;

use App\Models\Produk;
use App\Models\StokMutasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use RefreshDatabase;

    private function seedUsers(): array
    {
        return [
            'gudang' => User::create([
                'username'     => 'gdg',
                'password'     => bcrypt('x'),
                'nama_lengkap' => 'Budi',
                'level'        => 'Gudang',
                'shift'        => 'Pagi',
            ]),
            'manajer' => User::create([
                'username'     => 'mgr',
                'password'     => bcrypt('x'),
                'nama_lengkap' => 'Hendra',
                'level'        => 'Manajer',
                'shift'        => 'Pagi',
            ]),
            'kasir' => User::create([
                'username'     => 'ksr',
                'password'     => bcrypt('x'),
                'nama_lengkap' => 'Diani',
                'level'        => 'Kasir',
                'shift'        => 'Pagi',
            ]),
        ];
    }

    private function seedMutations(): Produk
    {
        $p = Produk::create(['nama' => 'Test Product', 'kategori' => 'Skincare']);
        // Beginning balance: +10 from purchase BEFORE the query window
        StokMutasi::create([
            'id_produk' => $p->id,
            'tanggal'   => '2026-04-15',
            'tipe'      => StokMutasi::TIPE_PEMBELIAN,
            'arah'      => StokMutasi::ARAH_IN,
            'qty'       => 10,
        ]);
        // In window (May 2026): +5 purchase, -2 sales
        StokMutasi::create([
            'id_produk' => $p->id,
            'tanggal'   => '2026-05-25',
            'tipe'      => StokMutasi::TIPE_PEMBELIAN,
            'arah'      => StokMutasi::ARAH_IN,
            'qty'       => 5,
        ]);
        StokMutasi::create([
            'id_produk' => $p->id,
            'tanggal'   => '2026-05-26',
            'tipe'      => StokMutasi::TIPE_SALES,
            'arah'      => StokMutasi::ARAH_OUT,
            'qty'       => 2,
        ]);
        return $p;
    }

    public function test_index_returns_rows_for_gudang(): void
    {
        $u = $this->seedUsers();
        $this->seedMutations();

        $res = $this->actingAs($u['gudang'])
            ->getJson('/api/inventory-movements?from=2026-05-01&to=2026-05-31');

        $res->assertOk()
            ->assertJsonStructure([
                'from', 'to', 'count',
                'rows' => [['id_produk', 'kode', 'nama', 'kategori',
                    'beginning', 'purchase_in', 'return_sales_in', 'barang_masuk_in',
                    'return_purchase_out', 'sales_out', 'real_sales_out',
                    'barang_keluar_out', 'ending']],
            ])
            ->assertJsonPath('count', 1)
            ->assertJsonPath('rows.0.beginning', 10)
            ->assertJsonPath('rows.0.purchase_in', 5)
            ->assertJsonPath('rows.0.sales_out', 2)
            ->assertJsonPath('rows.0.ending', 13);
    }

    public function test_index_allowed_for_manajer(): void
    {
        $u = $this->seedUsers();
        $this->seedMutations();
        $this->actingAs($u['manajer'])
            ->getJson('/api/inventory-movements?from=2026-05-01&to=2026-05-31')
            ->assertOk();
    }

    public function test_index_rejects_kasir_role(): void
    {
        $u = $this->seedUsers();
        $this->actingAs($u['kasir'])
            ->getJson('/api/inventory-movements?from=2026-05-01&to=2026-05-31')
            ->assertStatus(403);
    }

    public function test_index_validates_date_format(): void
    {
        $u = $this->seedUsers();
        $this->actingAs($u['gudang'])
            ->getJson('/api/inventory-movements?from=not-a-date&to=2026-05-31')
            ->assertStatus(422);
    }

    public function test_query_defaults_to_current_month(): void
    {
        $u = $this->seedUsers();
        $this->actingAs($u['gudang'])
            ->getJson('/api/inventory-movements')
            ->assertOk()
            ->assertJsonStructure(['from', 'to', 'count', 'rows']);
    }

    public function test_query_filters_out_mutations_outside_range(): void
    {
        $u = $this->seedUsers();
        $p = Produk::create(['nama' => 'P2', 'kategori' => 'Skincare']);
        // Mutations only in March.
        StokMutasi::create([
            'id_produk' => $p->id,
            'tanggal'   => '2026-03-10',
            'tipe'      => StokMutasi::TIPE_PEMBELIAN,
            'arah'      => StokMutasi::ARAH_IN,
            'qty'       => 7,
        ]);
        $res = $this->actingAs($u['gudang'])
            ->getJson('/api/inventory-movements?from=2026-05-01&to=2026-05-31')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('rows.0.beginning', 7)
            ->assertJsonPath('rows.0.purchase_in', 0)
            ->assertJsonPath('rows.0.ending', 7);
    }
}
