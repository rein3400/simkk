# SIM-KK D1-Readiness Design

## Goal

Optimasi `apps/api` agar production-ready di **Cloudflare D1** (database) + **Cloudflare R2** (foto klinis) — tanpa kehilangan dev velocity di SQLite lokal. Decided over Postgres/D1-only hybrid karena traffic 1 klinik Samarinda (1-5 transaksi/jam) tidak butuh overhead Postgres managed.

## Scope

- `apps/api` Laravel 13 app tetap di **VPS** dengan PHP 8.3 runtime (sesuai `deploy/cloudflare/02-vps-provision.md`).
- DB prod = Cloudflare D1, diakses dari VPS via HTTPS (D1 speaks SQLite wire protocol + HTTP API).
- Object storage prod = Cloudflare R2 via S3-compatible driver.
- DB dev = SQLite lokal (existing, tidak diubah).
- Foto klinis upload → R2 prod, fallback ke disk `local` jika R2 env kosong (existing behavior di `StorageService`).
- **NEW (2026-06-06)**: Schema migration untuk mendukung Daily Report (image 1) + Inventory Movements (image 2). Lihat sub-bagian "Daily Report + Inventory Movements Schema" di bawah.

## Architecture

```text
┌─────────────────────────┐         ┌──────────────────────────┐
│  DEV (laptop)           │         │  PROD (VPS + Cloudflare) │
│                         │         │                          │
│  apps/api               │         │  apps/api (PHP 8.3)      │
│  ├─ SQLite file         │ ──CI──> │  ├─ D1 (transaksional)  │
│  │   data/simkk.sqlite  │         │  └─ R2 (foto klinis)    │
│  └─ PHPUnit             │         │                          │
└─────────────────────────┘         └──────────────────────────┘
              ↑                                ↑
              └────── shared migrations ────────┘
                  (99% sama, 1% PRAGMA-only)
```

## Stack

- DB engine: SQLite 3.x (dev) / D1 (prod), diakses via Laravel 13 dengan custom connection
- Object storage: R2 via Laravel filesystem S3-compatible driver
- Backend runtime: Laravel 13 (existing) + PHP 8.3 di VPS
- PHPUnit untuk testing, 2 environment: `sqlite-fast` (in-memory) + `d1-local` (via wrangler)
- Deploy: wrangler CLI untuk D1 migrations + R2 token manual UI

## Schema Changes (5 perubahan D1-friendly)

### 0. Schema untuk Daily Report + Inventory Movements (BARU 2026-06-06)

PRD 3.3.1: "Ruang Tanda Tangan **Manajer dan Kasir**". Image 1 menampilkan Daily Report dengan multi-payment methods. Image 2 menampilkan Inventory Movements per barang per hari. Schema existing perlu ekstensi:

**Tabel baru `daily_cash_float`** (modal awal kasir per hari):
```sql
CREATE TABLE daily_cash_float (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,         -- kasir
  tanggal DATE NOT NULL,
  modal_awal INTEGER NOT NULL,      -- cash at cashier, started of day
  catatan TEXT,
  created_at TEXT, updated_at TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE UNIQUE INDEX idx_daily_cash_float_user_date ON daily_cash_float(user_id, tanggal);
```

**Tabel baru `daily_closing`** (workflow approval kasir → manajer):
```sql
CREATE TABLE daily_closing (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tanggal DATE NOT NULL UNIQUE,
  user_kasir_id INTEGER NOT NULL,
  submitted_at TEXT,
  user_manajer_id INTEGER,
  approved_at TEXT,
  status TEXT DEFAULT 'draft',      -- draft|submitted|approved|final
  total_penjualan INTEGER,
  total_card INTEGER,
  total_tunai INTEGER,
  pnl INTEGER,                       -- profit/loss hari itu
  setoran_bank INTEGER,              -- cash out ke rekening transit
  signature_kasir_path TEXT,         -- path ke R2 untuk TTD image
  signature_manajer_path TEXT,
  pdf_path TEXT,                     -- path ke R2 untuk generated PDF
  catatan TEXT,
  created_at TEXT, updated_at TEXT
);
```

**Modifikasi `transaksi.metode_bayar`** — sudah varchar 32, cukup. Tidak perlu enum. Daftar nilai yang dipakai:
- `Tunai` (cash)
- `Transfer BCA`, `Transfer Mandiri`
- `QRIS BCA`, `QRIS Mandiri`
- `EDC BCA`, `EDC Mandiri`, `EDC BCA Kasir`, `EDC Mandiri Kasir`

Migration: tambah komentar/konstanta di `app/Constants/MetodeBayar.php` untuk single source of truth.

**Modifikasi `produk.kategori`** — tambah kolom `kategori` jika belum ada (cek di migration `2026_06_01_000003_create_produk_table.php`). Image 1 mengelompokkan net sales per kategori: Facial Wash, Sunscreen, Premium, dll.

**Tabel baru `stok_mutasi`** (inventory movements harian):
```sql
CREATE TABLE stok_mutasi (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  id_produk INTEGER NOT NULL,
  tanggal DATE NOT NULL,
  tipe TEXT NOT NULL,               -- 'pembelian'|'return_purchase'|'return_sales'|'sales'|'barang_keluar'
  arah TEXT NOT NULL,               -- 'IN'|'OUT'
  qty REAL NOT NULL,
  id_batch INTEGER,                 -- FK ke batch_stok, NULL untuk retur
  id_transaksi INTEGER,             -- FK ke transaksi, NULL untuk pembelian
  catatan TEXT,
  created_at TEXT, updated_at TEXT
);
CREATE INDEX idx_stok_mutasi_produk_tanggal ON stok_mutasi(id_produk, tanggal);
CREATE INDEX idx_stok_mutasi_tipe_arah ON stok_mutasi(tipe, arah);
```

Tipe & arah:
- `pembelian` (IN) — dari `pembelian_supplier`
- `return_purchase` (OUT) — retur ke supplier
- `return_sales` (IN) — retur dari customer
- `sales` (OUT) — dari transaksi Lunas (auto-generated via trigger service)
- `barang_keluar` (OUT) — pemakaian internal/sample/service (manual entry)

**Modifikasi `users.signature_path`** — tambah kolom `signature_path` (nullable) untuk upload TTD image ke R2. Digunakan untuk render di PDF Daily Report (Kasir + Manajer sesuai PRD 3.3.1).

**Migration file**: `2026_06_06_120000_add_daily_report_and_inventory_movements.php` — single migration yang create 3 tabel baru + alter 3 tabel existing.

### 1. Foreign keys di D1 (off by default)

D1 tidak enforce FK secara default. Harus di-set per connection.

```php
// config/database.php (snippet)
'connections' => [
    'sqlite' => [
        'driver' => 'sqlite',
        'database' => env('DB_DATABASE', database_path('database.sqlite')),
        'foreign_key_constraints' => true,
        'busy_timeout' => 5000,
    ],
    'd1' => [
        'driver' => 'sqlite',
        'database' => env('D1_DATABASE_ID'),
        'foreign_key_constraints' => true,
        'prefix' => '',
    ],
],
```

### 2. Audit log: payload sebagai TEXT (JSON string)

Kolom `audit_log.payload` adalah TEXT (sudah di migration 2026_06_01_000012). Service layer pakai `json_encode`/`json_decode` PHP saat read/write. Tidak ada query `json_extract` di SQL (semua filtering dilakukan di PHP setelah load). Format payload didokumentasikan di `AuditService.php` docblock sebagai kontrak: `{actor, action, target_type, target_id, before, after, meta}`.

### 3. FIFO batch: composite partial index

```sql
CREATE INDEX idx_batch_stok_fifo_lookup
  ON batch_stok (id_produk, sisa_stok, tanggal_kadaluarsa)
  WHERE sisa_stok > 0;
```

Partial index `WHERE` clause jalan di SQLite ≥ 3.8 dan D1. Aman. Index lebih kecil & lebih cepat untuk query FIFO.

### 4. UUID untuk idempotency_keys

D1-friendly: TEXT PK, tidak butuh UUID v4 generator extension. Frontend generate UUID v4, backend simpan sebagai TEXT.

### 5. Migration cleanup

- Hapus migration yang pakai Postgres-only features (kalau ada, hasil audit).
- Wrap `CREATE INDEX` dalam transaction-safe form untuk D1.

## Out of Scope (YAGNI)

- Generated columns (D1 belum support sampai 2026)
- User triggers (D1 tidak support)
- `WITHER CHECK` di D1 (Laravel tidak emit)
- Postgres-style `RETURNING` (SQLite/D1 support tapi Laravel `update()` tidak pakai)
- Postgres JSONB, partial index khusus Postgres, advisory locks

## Test Strategy (3 environment, 1 test code)

```text
phpunit.xml.dist → defines 3 test suites:
├─ sqlite-fast   → driver=sqlite, in-memory, ~2s, runs on every save
├─ d1-local      → driver=sqlite, wrangler d1 --local, ~5s, runs on PR
└─ fixtures      → reusable seed factories
```

**`TestCase` base class auto-select driver dari env:**

```php
// tests/TestCase.php
protected function getEnvironmentSetUp($app): void {
    $driver = env('TEST_DB_DRIVER', 'sqlite');
    $app['config']->set('database.default', $driver);
}
```

**CI pipeline (`.github/workflows/test.yml`):**

```yaml
- run: php artisan test --testsuite=sqlite-fast    # gate: harus <10s
- run: npx wrangler d1 migrations apply simkk --local
- run: php artisan test --testsuite=d1-local       # gate: harus <30s
```

## Seed Strategy (production first-deploy)

**File: `database/seeders/ProductionBootstrapSeeder.php`**

| Tabel | Seed value | Reasoning |
|---|---|---|
| `users` | 4 user default: kasir/terapis/gudang/manajer, password `simkk-2026` | Owner klinik bisa langsung login + ganti password |
| `layanan` | 5 entry: Facial Basic, Chemical Peeling, Microneedling, Laser Rejuvenation, Treatment Acne | Contoh tarif agar UI tidak kosong |
| `produk` | 5 entry skincare: Sunscreen, Serum Vitamin C, Moisturizer, Toner, Retinol | Seed inventory agar FIFO bisa di-demo |
| `batch_stok` | 2 batch per produk dengan tanggal berbeda | Supaya FIFO logic visible di UI |
| `pembelian_supplier` (supplier) | 1 supplier default | Demo pembelian barang |
| `pasien` | kosong | Klinik input sendiri data pasien (privacy) |

**Idempotency**: seed pakai `firstOrCreate` + `updateOrInsert`, aman untuk re-run.

**Local dev override**: kalau `APP_ENV=local` → pakai `DevelopmentSeeder` (lebih banyak sample data untuk visual demo).

## Deploy Flow (D1 + R2 + Worker)

```bash
# 1. D1 migrations (sekali, manual)
npx wrangler d1 create simkk
npx wrangler d1 migrations apply simkk --remote

# 2. R2 bucket + token (sekali, manual UI)
# → dapat R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_ENDPOINT, R2_BUCKET

# 3. apps/api tetap di VPS dengan PHP runtime (per release)
# Deploy = push code + restart php-fpm
```

**Decision**: Laravel `apps/api` tetap di **VPS dengan PHP runtime** (sesuai `deploy/cloudflare/02-vps-provision.md`). D1 + R2 jadi **managed storage** yang diakses dari VPS. Bukan Laravel-on-Cloudflare-Workers (overkill).

## Rollback Plan

| Failure | Response |
|---|---|
| D1 migration breaks | `npx wrangler d1 migrations apply simkk --remote --dry-run` (preview), lalu revert migration lokal + commit fix |
| R2 unreachable | `StorageService` fallback ke disk `local` (sudah implemented) |
| Seed corrupts prod data | `firstOrCreate` + `updateOrInsert` = no-op kalau data sudah ada |
| VPS down | Health check + alarm; backup D1 daily via `wrangler d1 export simkk --remote --output backup.sql` |

## Effort Estimate

| Sub-task | Effort |
|---|---|
| `wrangler.toml` + D1 binding | 1-2 jam |
| Migration audit + 5 perubahan schema | 3-4 jam |
| **Schema migration untuk Daily Report + Inventory Movements (3 tabel baru + 3 alter) (2026-06-06)** | **4-5 jam** |
| `ProductionBootstrapSeeder` + dev override | 2 jam |
| **Seeder untuk metode bayar enum + sample stok_mutasi (2026-06-06)** | **1-2 jam** |
| CI workflow (GitHub Actions) | 2 jam |
| D1 deploy verification + smoke test | 1 jam |
| Doc update (ARCHITECTURE.md, CONTEXT.md, HALLUCINATION.md) | 1 jam |
| **Total** | **~15-19 jam kerja** |

## Acceptance Criteria

- [ ] `php artisan migrate:fresh --seed` di lokal masih jalan (regression check)
- [ ] `npx wrangler d1 migrations apply simkk --local` jalan tanpa error
- [ ] `php artisan test` 12+ tests pass di sqlite-fast
- [ ] `php artisan test --testsuite=d1-local` pass di wrangler d1
- [ ] R2 test: upload foto klinis → dapat URL publik (atau signed URL) → tampil di MedicalRecordView
- [ ] `ProductionBootstrapSeeder` jalan di D1 fresh, hasilnya 4 user + 5 layanan + 5 produk + 2 batch/produk + 1 supplier bisa di-query via D1 console
- [ ] **(2026-06-06) Migration `2026_06_06_120000_add_daily_report_and_inventory_movements.php` create 3 tabel + alter 3 tabel tanpa error**
- [ ] **(2026-06-06) `users.signature_path` bisa di-upload via profile UI, image tersimpan di R2**
- [ ] **(2026-06-06) `daily_closing` workflow: draft → submitted → approved → final, dengan TTD kasir + manajer (PRD 3.3.1)**
- [ ] **(2026-06-06) `stok_mutasi` auto-logged saat `transaksi.status` jadi `Lunas` (sales OUT) dan `pembelian_supplier` baru (pembelian IN)**
- [ ] **(2026-06-06) Service: `DailyReportService::generate($tanggal)` return PDF sesuai image 1 (KOP, net sales per kategori, multi-payment, P&L, TTD dual)**
- [ ] **(2026-06-06) Service: `InventoryMovementService::query($from, $to)` return rows sesuai image 2 (per barang per hari, range filter)**
- [ ] ARCHITECTURE.md, CONTEXT.md, HALLUCINATION.md di-update dengan status D1
- [ ] Spec ini di-commit ke git
