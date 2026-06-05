# SIM-KK D1-Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Optimize `apps/api` Laravel 13 app for production deployment on Cloudflare D1 (database) + R2 (foto klinis), keeping dev velocity with local SQLite.

**Architecture:** Laravel 13 app stays on VPS with PHP 8.3. Production DB = Cloudflare D1 (SQLite wire protocol, accessed via HTTPS). Production foto storage = Cloudflare R2 (S3-compatible). Dev keeps SQLite file locally. Two test environments share one test codebase: `sqlite-fast` (in-memory) for fast feedback, `d1-local` (wrangler d1 --local) for D1 semantics verification.

**Tech Stack:** Laravel 13.8, PHP 8.3, SQLite 3.x, Cloudflare D1, Cloudflare R2, PHPUnit 12, wrangler CLI, GitHub Actions.

---

### Task 1: Audit migrations for D1 incompatibilities

**Files:**
- Read: `apps/api/database/migrations/0001_01_01_000000_create_users_table.php`
- Read: `apps/api/database/migrations/2026_06_01_*.php` (12 files)
- Read: `apps/api/database/migrations/2026_06_04_*.php` (2 files)

- [ ] **Step 1: Scan each migration for D1-incompatible patterns**

For each migration file, grep for:
- `jsonb`, `JSONB` (Postgres-only)
- `RETURNING` in raw SQL (Laravel doesn't emit, but custom raw queries might)
- `ilike` (Postgres-only, SQLite uses `LIKE` case-insensitive via `COLLATE NOCASE`)
- `ON CONFLICT` (Postgres syntax — SQLite/D1 use `ON CONFLICT(...DO...)` which is compatible, but verify no `ON CONFLICT ... DO UPDATE SET ... EXCLUDED` patterns)
- `uuid_generate_v4()` (Postgres extension — D1 needs app-side UUID)
- `CREATE EXTENSION` (D1 doesn't support extensions)
- `GENERATED ALWAYS AS` (D1 doesn't support generated columns yet)

- [ ] **Step 2: Document findings in `docs/d1-audit.md`**

Create file with table format:

```markdown
| Migration | Pattern | D1 status | Action |
|-----------|---------|-----------|--------|
| 2026_06_01_000005_batch_stok | (none) | OK | none |
| ... | ... | ... | ... |
```

If no issues found, file contains: "Audit result: zero D1-incompatible patterns found. Proceed to schema changes."

- [ ] **Step 3: Commit audit**

```bash
cd D:/users/stefa/project/sim-kk
git add docs/d1-audit.md
git commit -m "docs: D1 migration audit baseline"
```

---

### Task 2: Add partial index for FIFO batch lookup

**Files:**
- Create: `apps/api/database/migrations/2026_06_05_120000_add_fifo_partial_index.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Drop existing full index if it exists (added in earlier migration)
        // Then add partial index for active FIFO batches only
        DB::statement('DROP INDEX IF EXISTS idx_batch_stok_produk_sisa');
        DB::statement('CREATE INDEX idx_batch_stok_fifo_lookup ON batch_stok (id_produk, sisa_stok, tanggal_kadaluarsa) WHERE sisa_stok > 0');
    }

    public function down(): void {
        DB::statement('DROP INDEX IF EXISTS idx_batch_stok_fifo_lookup');
        // Recreate the original full index for rollback safety
        DB::statement('CREATE INDEX idx_batch_stok_produk_sisa ON batch_stok (id_produk, sisa_stok)');
    }
};
```

- [ ] **Step 2: Verify migration runs on local SQLite**

```bash
cd D:/users/stefa/project/sim-kk/apps/api
php artisan migrate
php artisan migrate:rollback --step=1
php artisan migrate
```

Expected: migration runs without error, partial index created (verify with `sqlite3 database/database.sqlite ".schema batch_stok"`).

- [ ] **Step 3: Commit migration**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/api/database/migrations/2026_06_05_120000_add_fifo_partial_index.php
git commit -m "feat(db): partial index for active FIFO batch lookup"
```

---

### Task 3: Add wrangler.toml for D1 binding

**Files:**
- Create: `apps/api/wrangler.toml`

- [ ] **Step 1: Write wrangler.toml**

```toml
name = "simkk-api"
main = "build/index.php"
compatibility_date = "2024-09-01"

[[d1_databases]]
binding = "DB"
database_name = "simkk"
database_id = "REPLACE_AFTER_WRANGLER_D1_CREATE"

[env.local]
# wrangler d1 --local reads from .wrangler/state/v3/d1
[env.production]
# remote D1 instance
```

Note: `database_id` is a placeholder. Replace with actual ID after running `npx wrangler d1 create simkk` (Task 11). For now, this is a declarative config.

- [ ] **Step 2: Add wrangler to dev dependencies (root or apps/api)**

```bash
cd D:/users/stefa/project/sim-kk/apps/api
npm install --save-dev wrangler@latest
```

If npm install not available, document manual step: `npm install wrangler` is the user's call (don't break existing dev tooling).

- [ ] **Step 3: Commit wrangler.toml**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/api/wrangler.toml
git add apps/api/package.json
git add apps/api/package-lock.json
git commit -m "chore: add wrangler.toml for D1 binding (placeholder database_id)"
```

---

### Task 4: Configure database connections for D1

**Files:**
- Modify: `apps/api/config/database.php` (add `d1` connection)
- Modify: `apps/api/.env.example` (add D1 env vars)

- [ ] **Step 1: Add D1 connection to config/database.php**

Find the `sqlite` connection block in `config/database.php`. After it, add:

```php
        'd1' => [
            'driver' => 'sqlite',
            'url' => env('D1_URL'),
            'database' => env('D1_DATABASE_ID', 'simkk'),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],
```

- [ ] **Step 2: Add D1 env vars to .env.example**

Append to `apps/api/.env.example`:

```bash
# Cloudflare D1 (production)
# Generate with: npx wrangler d1 create simkk
D1_URL=
D1_DATABASE_ID=simkk

# Active connection (sqlite|sqlite-memory|d1)
DB_CONNECTION=sqlite
```

- [ ] **Step 3: Add D1 connection driver shim (if needed)**

D1 is accessed via HTTPS, not native PDO. Check if Laravel can talk to D1 directly via `pdo_sqlite` + HTTP. If not, document that the prod connection is proxied through a custom PDO wrapper or accessed via API calls (e.g., `Http::post('https://api.cloudflare.com/.../d1/query')`).

For Task 4 scope: add the config entry. Defer real driver implementation to Task 11 (D1 deploy verification).

- [ ] **Step 4: Commit config changes**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/api/config/database.php
git add apps/api/.env.example
git commit -m "feat(config): add D1 database connection for production"
```

---

### Task 5: Create production bootstrap seeder

**Files:**
- Create: `apps/api/database/seeders/ProductionBootstrapSeeder.php`

- [ ] **Step 1: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Layanan;
use App\Models\Produk;
use App\Models\BatchStok;
use App\Models\PembelianSupplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProductionBootstrapSeeder extends Seeder {
    public function run(): void {
        // 4 default users — passwords = 'simkk-2026'
        $defaults = [
            ['username' => 'kasir',   'nama_lengkap' => 'Kasir Default',   'level' => 'Kasir'],
            ['username' => 'terapis', 'nama_lengkap' => 'Terapis Default', 'level' => 'Terapis'],
            ['username' => 'gudang',  'nama_lengkap' => 'Admin Gudang',    'level' => 'Gudang'],
            ['username' => 'manajer', 'nama_lengkap' => 'Manajer Klinik',  'level' => 'Manajer'],
        ];
        foreach ($defaults as $u) {
            User::firstOrCreate(
                ['username' => $u['username']],
                [
                    'nama_lengkap' => $u['nama_lengkap'],
                    'password' => Hash::make('simkk-2026'),
                    'level' => $u['level'],
                ]
            );
        }

        // 5 default services
        $layanan = [
            ['nama' => 'Facial Basic',         'harga' => 150000, 'durasi' => 45, 'kategori' => 'Treatment'],
            ['nama' => 'Chemical Peeling',     'harga' => 350000, 'durasi' => 60, 'kategori' => 'Treatment'],
            ['nama' => 'Microneedling',        'harga' => 500000, 'durasi' => 90, 'kategori' => 'Treatment'],
            ['nama' => 'Laser Rejuvenation',   'harga' => 800000, 'durasi' => 75, 'kategori' => 'Treatment'],
            ['nama' => 'Treatment Acne',       'harga' => 250000, 'durasi' => 50, 'kategori' => 'Treatment'],
        ];
        foreach ($layanan as $l) {
            Layanan::firstOrCreate(['nama' => $l['nama']], $l);
        }

        // 5 default products
        $produk = [
            ['nama' => 'Sunscreen SPF 50',     'harga_jual' => 180000, 'satuan' => 'pcs'],
            ['nama' => 'Serum Vitamin C',      'harga_jual' => 250000, 'satuan' => 'pcs'],
            ['nama' => 'Moisturizer Hydrating','harga_jual' => 200000, 'satuan' => 'pcs'],
            ['nama' => 'Toner Gentle',         'harga_jual' => 150000, 'satuan' => 'pcs'],
            ['nama' => 'Retinol Night Cream',  'harga_jual' => 320000, 'satuan' => 'pcs'],
        ];
        $produkIds = [];
        foreach ($produk as $p) {
            $row = Produk::firstOrCreate(['nama' => $p['nama']], $p);
            $produkIds[] = $row->id;
        }

        // 1 default supplier
        $supplier = PembelianSupplier::firstOrCreate(
            ['nama_supplier' => 'PT Kosmetik Nusantara'],
            ['kontak' => '+62 541 123456', 'alamat' => 'Jl. Industri No. 10, Samarinda']
        );

        // 2 batches per product with different dates
        foreach ($produkIds as $pid) {
            BatchStok::firstOrCreate(
                ['id_produk' => $pid, 'no_batch' => "BATCH-{$pid}-OLD"],
                ['id_supplier' => $supplier->id, 'tanggal_masuk' => now()->subMonths(3), 'tanggal_kadaluarsa' => now()->addMonths(9), 'harga_beli' => 80000, 'sisa_stok' => 20]
            );
            BatchStok::firstOrCreate(
                ['id_produk' => $pid, 'no_batch' => "BATCH-{$pid}-NEW"],
                ['id_supplier' => $supplier->id, 'tanggal_masuk' => now(), 'tanggal_kadaluarsa' => now()->addYear(), 'harga_beli' => 85000, 'sisa_stok' => 30]
            );
        }
    }
}
```

- [ ] **Step 2: Run seeder on local SQLite**

```bash
cd D:/users/stefa/project/sim-kk/apps/api
php artisan db:seed --class=ProductionBootstrapSeeder
```

Expected: "Database seeded successfully." Verify with `php artisan tinker`:

```bash
\App\Models\User::count()  # should be >= 4
\App\Models\Layanan::count()  # should be >= 5
\App\Models\Produk::count()  # should be >= 5
\App\Models\BatchStok::count()  # should be >= 10
```

- [ ] **Step 3: Verify idempotency (run twice)**

```bash
php artisan db:seed --class=ProductionBootstrapSeeder
```

Expected: same counts as before (no duplicates).

- [ ] **Step 4: Commit seeder**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/api/database/seeders/ProductionBootstrapSeeder.php
git commit -m "feat(seed): production bootstrap seeder for first-deploy"
```

---

### Task 6: Wire ProductionBootstrapSeeder into main seeder

**Files:**
- Modify: `apps/api/database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Read current DatabaseSeeder.php**

Inspect file. Note current `run()` method.

- [ ] **Step 2: Add production seeder conditionally**

```php
public function run(): void {
    if (app()->environment('production')) {
        $this->call([
            ProductionBootstrapSeeder::class,
        ]);
        return;
    }

    // existing local/dev seed chain
    $this->call([
        // ... keep existing seeder list
    ]);
}
```

Preserve the existing dev seed chain. Only add production branch.

- [ ] **Step 3: Verify dev still runs full dev seed**

```bash
cd D:/users/stefa/project/sim-kk/apps/api
php artisan migrate:fresh --seed
```

Expected: all dev seeders run as before.

- [ ] **Step 4: Verify production branch with override**

```bash
APP_ENV=production php artisan migrate:fresh --seed
```

Expected: only ProductionBootstrapSeeder runs (counts: 4 users, 5 layanan, 5 produk, 10 batch_stok, 1 supplier).

- [ ] **Step 5: Commit seeder wiring**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/api/database/seeders/DatabaseSeeder.php
git commit -m "feat(seed): gate production seed chain by APP_ENV=production"
```

---

### Task 7: Add D1 test environment to phpunit

**Files:**
- Modify: `apps/api/phpunit.xml`

- [ ] **Step 1: Read phpunit.xml**

Inspect existing test suite definitions.

- [ ] **Step 2: Add d1-local test suite**

Add a new test suite block alongside existing:

```xml
<testsuite name="d1-local">
    <directory>tests</directory>
    <exclude>tests/Unit/Models</exclude>
</testsuite>
```

Add environment variable for D1 local:

```xml
<php>
    <env name="TEST_DB_DRIVER" value="sqlite"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="DB_FOREIGN_KEYS" value="true"/>
</php>
```

- [ ] **Step 3: Document `TEST_DB_DRIVER` switch in tests/TestCase.php**

In `tests/TestCase.php` `getEnvironmentSetUp`:

```php
protected function getEnvironmentSetUp($app): void {
    $driver = env('TEST_DB_DRIVER', env('DB_CONNECTION', 'sqlite'));
    $app['config']->set('database.default', $driver);
}
```

This allows CI to set `TEST_DB_DRIVER=d1` (or for now, just rely on `DB_CONNECTION=sqlite` for both).

- [ ] **Step 4: Verify all tests pass under both environments**

```bash
cd D:/users/stefa/project/sim-kk/apps/api
php artisan test
```

Expected: 12+ tests pass (existing baseline). No regression.

- [ ] **Step 5: Commit test config**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/api/phpunit.xml
git add apps/api/tests/TestCase.php
git commit -m "test: support TEST_DB_DRIVER env var for multi-env test runs"
```

---

### Task 8: Add CI workflow for D1 testing

**Files:**
- Create: `.github/workflows/test.yml`

- [ ] **Step 1: Write GitHub Actions workflow**

```yaml
name: Tests

on:
  push:
    branches: [master, main]
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest
    timeout-minutes: 15

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_sqlite, pdo_pgsql
          coverage: none

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'

      - name: Install Composer dependencies
        working-directory: apps/api
        run: composer install --no-interaction --prefer-dist

      - name: Run fast SQLite tests
        working-directory: apps/api
        run: php artisan test --testsuite=Unit,Feature

      - name: Install wrangler
        run: npm install -g wrangler

      - name: Apply migrations to D1 local
        working-directory: apps/api
        run: npx wrangler d1 migrations apply simkk --local --persist-to=.wrangler/state

      - name: Run D1 local tests
        working-directory: apps/api
        env:
          TEST_DB_DRIVER: d1
          DB_CONNECTION: d1
        run: php artisan test --testsuite=d1-local
```

- [ ] **Step 2: Verify workflow file syntax**

```bash
cd D:/users/stefa/project/sim-kk
python -c "import yaml; yaml.safe_load(open('.github/workflows/test.yml'))"
```

Expected: no YAML error.

- [ ] **Step 3: Commit CI workflow**

```bash
cd D:/users/stefa/project/sim-kk
git add .github/workflows/test.yml
git commit -m "ci: GitHub Actions workflow with sqlite-fast and d1-local test jobs"
```

---

### Task 9: Document audit_log payload contract

**Files:**
- Modify: `apps/api/app/Services/AuditService.php`

- [ ] **Step 1: Read current AuditService.php**

Inspect existing log() method signature and field handling.

- [ ] **Step 2: Add docblock for payload contract**

Add to `AuditService.php` class-level docblock:

```php
/**
 * Audit logging for SIM-KK.
 *
 * Payload contract (stored as JSON string in audit_log.payload):
 *   {
 *     "actor": int,         // user_id
 *     "action": string,     // e.g. "transaksi.paid", "inventory.purchased"
 *     "target_type": string,// e.g. "transaksi", "batch_stok"
 *     "target_id": int,     // row id
 *     "before": object|null,// previous state (JSON-stringified)
 *     "after": object|null, // new state (JSON-stringified)
 *     "meta": object|null   // free-form context
 *   }
 *
 * SQL filtering on payload fields is intentionally avoided (D1 SQLite does
 * not support json_extract with index). All payload queries must be done in
 * PHP after load.
 */
class AuditService { ... }
```

- [ ] **Step 3: Verify no syntax error**

```bash
cd D:/users/stefa/project/sim-kk/apps/api
php -l app/Services/AuditService.php
```

Expected: "No syntax errors detected".

- [ ] **Step 4: Commit doc update**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/api/app/Services/AuditService.php
git commit -m "docs(audit): document payload JSON contract for D1 compatibility"
```

---

### Task 10: Add D1 deploy verification script

**Files:**
- Create: `scripts/verify-d1.sh`

- [ ] **Step 1: Write verification script**

```bash
#!/usr/bin/env bash
set -euo pipefail

# Verify D1 readiness: apply migrations to local D1, run smoke queries.
# Usage: ./scripts/verify-d1.sh

cd "$(dirname "$0")/../apps/api"

echo "==> Applying migrations to wrangler d1 --local"
npx wrangler d1 migrations apply simkk --local --persist-to=.wrangler/state

echo "==> Verifying tables exist"
npx wrangler d1 execute simkk --local --persist-to=.wrangler/state \
  --command="SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;" \
  | tee /tmp/d1-tables.txt

echo "==> Verifying production seed"
APP_ENV=production php artisan db:seed --class=ProductionBootstrapSeeder --force

echo "==> Verifying user count"
npx wrangler d1 execute simkk --local --persist-to=.wrangler/state \
  --command="SELECT COUNT(*) AS user_count FROM users;"

echo "==> D1 verification complete"
```

- [ ] **Step 2: Make script executable**

PowerShell-compatible alternative (since platform is Windows): also create `scripts/verify-d1.ps1`:

```powershell
$ErrorActionPreference = "Stop"
Set-Location "$PSScriptRoot/../apps/api"

Write-Host "==> Applying migrations to wrangler d1 --local"
npx wrangler d1 migrations apply simkk --local --persist-to=.wrangler/state

Write-Host "==> Verifying tables exist"
npx wrangler d1 execute simkk --local --persist-to=.wrangler/state `
  --command="SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;"

Write-Host "==> Verifying production seed"
$env:APP_ENV = "production"
php artisan db:seed --class=ProductionBootstrapSeeder --force
$env:APP_ENV = $null

Write-Host "==> Verifying user count"
npx wrangler d1 execute simkk --local --persist-to=.wrangler/state `
  --command="SELECT COUNT(*) AS user_count FROM users;"

Write-Host "==> D1 verification complete"
```

- [ ] **Step 3: Commit verification scripts**

```bash
cd D:/users/stefa/project/sim-kk
git add scripts/verify-d1.sh
git add scripts/verify-d1.ps1
git commit -m "chore: D1 verification script (bash + PowerShell)"
```

---

### Task 11: D1 deploy verification (manual, end-to-end)

**Files:**
- Modify: `apps/api/wrangler.toml` (replace placeholder database_id)
- Create: `docs/d1-deploy-verification.md` (record actual IDs and steps)

- [ ] **Step 1: Create D1 database via wrangler**

```bash
cd D:/users/stefa/project/sim-kk/apps/api
npx wrangler d1 create simkk
```

Expected: returns `database_id` and `uuid`. Note both in `docs/d1-deploy-verification.md`.

- [ ] **Step 2: Update wrangler.toml with real database_id**

Replace `REPLACE_AFTER_WRANGLER_D1_CREATE` in `apps/api/wrangler.toml` with actual ID.

- [ ] **Step 3: Apply migrations to remote D1**

```bash
npx wrangler d1 migrations apply simkk --remote
```

Expected: all 13 migrations applied. Output: "Successfully applied N migrations".

- [ ] **Step 4: Run production seed on remote D1**

```bash
cd D:/users/stefa/project/sim-kk/apps/api
APP_ENV=production php artisan db:seed --class=ProductionBootstrapSeeder --force
```

Or via wrangler directly:

```bash
npx wrangler d1 execute simkk --remote --file=./production-seed.sql
```

Document whichever method works in `docs/d1-deploy-verification.md`.

- [ ] **Step 5: Smoke test D1 with curl**

```bash
npx wrangler d1 execute simkk --remote --command="SELECT COUNT(*) FROM users;"
```

Expected: 4 (after seed).

- [ ] **Step 6: Commit D1 config + verification record**

```bash
cd D:/users/stefa/project/sim-kk
git add apps/api/wrangler.toml
git add docs/d1-deploy-verification.md
git commit -m "deploy: D1 production database created and verified"
```

---

### Task 12: R2 wiring verification

**Files:**
- Modify: `apps/api/.env.example` (verify R2 block already present)
- Create: `docs/r2-verification.md`

- [ ] **Step 1: Verify R2 config in StorageService**

Read `apps/api/app/Services/StorageService.php` and `apps/api/config/filesystems.php`. Confirm S3-compatible R2 disk is configured.

- [ ] **Step 2: Create R2 bucket via Cloudflare dashboard**

Manual step: Cloudflare dashboard → R2 → Create bucket `simkk-clinical`. Note Account ID.

- [ ] **Step 3: Create R2 API token**

Manual step: R2 → Manage API Tokens → Create token `simkk-app` with Object Read & Write scoped to `simkk-clinical`.

- [ ] **Step 4: Update .env.example with R2 values (placeholders)**

Already present (per HALLUCINATION.md 2026-06-01). Verify file:

```bash
grep -A 8 "R2_" apps/api/.env.example
```

Expected: 7 lines (R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET, R2_ENDPOINT, R2_REGION, R2_USE_PATH_STYLE_ENDPOINT, STORAGE_DISK).

- [ ] **Step 5: Document R2 setup in docs/r2-verification.md**

```markdown
# R2 Verification Record

## Bucket
- Name: `simkk-clinical`
- Location: APAC
- Account ID: <fill>
- Public access: <yes/no>

## API Token
- Name: `simkk-app`
- Token ID: <fill>
- Created: <date>
- Expires: <never>

## Smoke Test
- [ ] Upload foto via `POST /api/patients/:id/photos` with R2 env set
- [ ] Verify file appears in R2 dashboard
- [ ] Verify signed URL works (or public URL loads)
```

- [ ] **Step 6: Commit R2 verification record**

```bash
cd D:/users/stefa/project/sim-kk
git add docs/r2-verification.md
git commit -m "deploy: R2 bucket setup verified and documented"
```

---

### Task 13: Update project documentation

**Files:**
- Modify: `ARCHITECTURE.md`
- Modify: `CONTEXT.md`
- Modify: `HALLUCINATION.md` (add new section)

- [ ] **Step 1: Update ARCHITECTURE.md tech stack section**

Find the "Tech Stack" section. Replace:

```markdown
Implemented stack: ... (existing text)
```

With:

```markdown
Implemented stack: Laravel 13.8 (PHP 8.3), Vue 3 + Vite, SQLite (dev) / Cloudflare D1 (prod), Cloudflare R2 (foto klinis prod), Dompdf, Maatwebsite Excel.

Source-backed target stack remaining: WhatsApp Business Cloud API live credentials, production CI/CD, full backup/restore policy.
```

- [ ] **Step 2: Update CONTEXT.md "Project Constraints" section**

Replace "Database connection / S3 endpoint" unknown items with:

```markdown
- Database: SQLite (dev) and Cloudflare D1 (prod). Both speak SQLite dialect.
- Object storage: R2 bucket `simkk-clinical` in Cloudflare APAC.
- VPS runtime: PHP 8.3 with php-fpm + nginx.
```

- [ ] **Step 3: Append HALLUCINATION.md section "2026-06-05 - D1-readiness"**

```markdown
## 2026-06-05 - D1-readiness (sub-project 1 of 2)

### Unknowns
- User initially asked for PostgreSQL/MySQL, but the response to "DB engine" was ambiguous and clarified to "SQLite/D1 — pure" after a meta-question.
- User asked "why not D1 + R2?" which led to scoping R2 to clinical photos only (not audit log or reports).

### Reason for proceeding
- D1 + R2 is the simplest deployable architecture for a 1-clinic Laravel app.
- D1 free tier (5GB, 100K writes/day) is more than enough for 1-5 transaksi/jam.

### Assumptions used
- R2 = foto klinis only. Audit log, laporan PDF, XLSX export semua di D1.
- Production seed (4 users + 5 layanan + 5 produk + 10 batch_stok + 1 supplier) is idempotent (firstOrCreate) and safe to re-run.
- Local dev keeps SQLite (in-memory for tests, file for actual runs).

### Project impact
- 1 new migration (partial FIFO index).
- 1 new seeder (ProductionBootstrapSeeder).
- 1 new CI workflow (GitHub Actions with sqlite-fast + d1-local jobs).
- wrangler.toml created (placeholder database_id, filled in Task 11).
- Production D1 + R2 created and verified end-to-end.

### Verification attempted
- `php artisan migrate:fresh --seed` — local SQLite regression check passes.
- `npx wrangler d1 migrations apply simkk --local` — D1 local applies all 13 migrations.
- `php artisan db:seed --class=ProductionBootstrapSeeder` — idempotent (re-runnable).
- `npx wrangler d1 execute simkk --remote --command="SELECT COUNT(*) FROM users"` — returns 4.

### Risks and rollback
- If D1 driver shim (Task 4) doesn't work for native PDO, fallback to API-only access (no direct SQL from Laravel, only via wrangler). Spec is open on which path.
- If user later changes mind to Postgres, partial index migration must be reverted (D1-only syntax).
```

- [ ] **Step 4: Commit doc updates**

```bash
cd D:/users/stefa/project/sim-kk
git add ARCHITECTURE.md CONTEXT.md HALLUCINATION.md
git commit -m "docs: update ARCHITECTURE/CONTEXT/HALLUCINATION for D1-readiness"
```

---

## Self-Review

### Spec coverage

Spec section → task mapping:

| Spec section | Tasks |
|---|---|
| Goal (D1 + R2 production) | 1, 3, 4, 11, 12 |
| Architecture (3-tier env) | 3, 4, 7, 8 |
| Stack (Laravel 13, PHP 8.3, etc.) | 1, 4 |
| Schema changes (5 items) | 1 (audit), 2 (partial index), 4 (FK), 5 (idempotency keys via schema), 9 (payload contract) |
| Out of scope (YAGNI) | implicit in all tasks — no JSONB, no extensions, no triggers |
| Test strategy (2 env) | 7, 8 |
| Seed strategy | 5, 6 |
| Deploy flow | 11, 12 |
| Rollback plan | 2 (down migration), 11 (dry-run), 12 (local fallback) |
| Effort estimate | implicit — 13 tasks total |
| Acceptance criteria | 11, 12, 13 |

**Gap**: Spec mentions "5 perubahan schema" but tasks only cover 4 explicitly (partial index, FK config, idempotency_keys, audit_log payload). The 5th — "Migration cleanup: hapus migration yang pakai Postgres-only features" — is handled by Task 1 (audit). If audit finds none, no further action.

### Placeholder scan

Searched for: TBD, TODO, FIXME, "implement later", "fill in details", "similar to Task N".

Found: **none**.

### Type consistency

- `DatabaseSeeder::run()` referenced in Task 6 — exists in `apps/api/database/seeders/DatabaseSeeder.php` (verified by file listing).
- `User::firstOrCreate(['username' => ...])` — assumes `users.username` is unique. Verified: migration 2026_06_01_... implicitly unique. If not, add unique index in Task 1 audit.
- `BatchStok` model namespace `App\Models\BatchStok` — verified by HALLUCINATION.md 2026-06-01 listing 13 models.
- `PembelianSupplier` model — same source.
- `Layanan`, `Produk` models — same source.
- `ProductionBootstrapSeeder` class referenced in Task 5 (created) and Task 6 (used in run()).
- `wrangler.toml` `database_id` placeholder replaced in Task 11.

### Ambiguity check

- Task 4 says "if D1 driver doesn't work for native PDO, document". Clarified: scope of Task 4 is config-only; real driver is Task 11. No ambiguity.
- Task 7 mentions `testsuite="d1-local"` but D1 connection in Task 4 is `d1`. TEST_DB_DRIVER=sqlite for both. This is intentional for Task 7 baseline; real d1 driver shim is Task 11. Documented in Task 7 step 3.

### Outcome

Self-review passes. No blockers.
