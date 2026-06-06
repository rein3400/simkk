# GB-MIGRATION: Migration Parity (sqlite vs D1)

**Date**: 2026-06-06
**Status**: Informational — D1 deployment not implemented in this codebase.

## Context

`apps/api` is a Laravel 13 application with SQLite (`:memory:` in tests, file in dev).
The architecture target documented in `ARCHITECTURE.md` mentions Cloudflare D1 as the
production database, but the codebase has **no D1 configuration, no `wrangler.toml`,
no `cloudflare/d1` driver** wired up.

```bash
$ grep -r "wrangler\|cloudflare.*d1" apps/api/composer.json apps/api/config/
# (no output — no D1 driver)
```

## Migrations inventory

`apps/api/database/migrations/`:

| Migration | Table | Pattern |
|-----------|-------|---------|
| 0001_01_01_000000_create_users_table | users, password_reset_tokens, sessions | standard |
| 0001_01_01_000001_create_cache_table | cache, cache_locks | standard |
| 0001_01_01_000002_create_jobs_table | jobs, job_batches, failed_jobs | standard |
| 2026_06_01_000001_create_pasien_table | pasien | standard |
| 2026_06_01_000002_create_terapis_table | terapis | standard |
| 2026_06_01_000003_create_produk_table | produk | standard |
| 2026_06_01_000004_create_layanan_table | layanan | standard |
| 2026_06_01_000005_create_batch_stok_table | batch_stok | standard |
| 2026_06_01_000006_create_pembelian_supplier_table | pembelian_supplier | standard |
| 2026_06_01_000007_create_transaksi_table | transaksi | standard |
| 2026_06_01_000008_create_transaksi_detail_table | transaksi_detail | FK + unique |
| 2026_06_01_000009_create_buku_kas_table | buku_kas | FK |
| 2026_06_01_000010_create_catatan_treatment_table | catatan_treatment | standard |
| 2026_06_01_000011_create_foto_klinis_table | foto_klinis | standard |
| 2026_06_01_000012_create_audit_log_table | audit_log | standard |
| 2026_06_01_141213_create_personal_access_tokens_table | personal_access_tokens | morphs() |
| 2026_06_04_120100_create_idempotency_keys_table | idempotency_keys | unique + index |
| 2026_06_04_120200_add_assigned_terapis_to_pasien | (alter) | FK add |
| 2026_06_06_120000_add_telegram_chat_id_to_pasien | (alter) | column add |
| 2026_06_06_130000_add_daily_report_and_inventory_movements | daily_cash_float, daily_closing, stok_mutasi, (others) | mixed |

## D1-incompatibility analysis

D1 is Cloudflare's serverless SQLite, with these constraints:
- `STRICT` tables only (no type affinity escape hatches)
- No `ALTER TABLE ... DROP COLUMN` in some old versions (newer has it)
- Limited JSON ops (no JSON1 in some configurations)
- No `lockForUpdate()` — D1 is single-writer; serialization is automatic per database

### Patterns in this codebase that **may** break on D1

1. **`lockForUpdate()` in `TransaksiService::decrementStock`**
   - `BatchStok::where(...)->lockForUpdate()->get();`
   - D1 single-writer handles this implicitly; `lockForUpdate()` will work but is a no-op.
   - **Severity**: low. Code is safe on D1; just unnecessary.

2. **JSON columns (`data_lama`, `data_baru`, `response_body`)**
   - SQLite: stores as TEXT, no validation. Laravel `casts:['data_lama' => 'array']` decodes.
   - D1: with `STRICT` mode, must use JSON-affinity column. Without STRICT, same as SQLite.
   - **Severity**: low if D1 is non-strict; medium if strict.

3. **`morphs('tokenable')` in `personal_access_tokens`**
   - Creates `tokenable_id` (UNSIGNED BIGINT) + `tokenable_type` (VARCHAR).
   - D1 fully supports this.
   - **Severity**: none.

4. **Composite unique + multiple indexes**
   - `idempotency_keys`: `unique(['user_id', 'key_hash', 'endpoint'])`, `index('created_at')`.
   - D1 supports this; just verify index naming.
   - **Severity**: none.

5. **Foreign key cascade rules**
   - `cascadeOnDelete()` and `nullOnDelete()` everywhere.
   - D1 supports FKs when configured.
   - **Severity**: low.

6. **`decimal('komisi_rate', 4, 2)`**
   - SQLite stores as TEXT, D1 stores as REAL.
   - `komisi_rate` is read as float; arithmetic in `TransaksiService::pay` uses `(float) $layanan->komisi_rate`.
   - **Severity**: low; the float→int round is already handled.

7. **Long-text columns (`text('catatan')`, `text('deskripsi')`)**
   - SQLite: unlimited length. D1: limited (~2KB by default? no, D1 has no per-row limit; it has a hard 1MB row limit).
   - **Severity**: low. Terapis note `catatan` is capped at 5000 chars in validation; OK.

8. **`$table->date('kadaluarsa')->nullable()`**
   - Standard, supported.
   - **Severity**: none.

### What would need to change for actual D1 deployment

- Add `cloudflare/d1` or D1 HTTP API client to composer
- Configure `config/database.php` with `d1` driver
- Configure `config/database.php` to use Cloudflare account ID + database ID
- Set up D1 migrations runner (Laravel does not natively support D1; need
  `wrangler d1 migrations apply` or HTTP-based migration loader)
- Disable `lockForUpdate()` (D1 single-writer handles it)
- Verify all JSON columns are read via `->json_value()` (D1 may need this) or
  use `->value('column')` with manual json_decode

## Verdict

The current migration set is **largely D1-compatible**, but the **deployment
plumbing** is not present. No D1 driver, no wrangler config, no D1-aware
migration runner. The migration files themselves are vanilla Laravel — no
D1-specific code or syntax — so they would run as-is via a generic D1
migration loader if one were added.

**Severity of gap**: medium (deployment readiness, not migration correctness).
