# Architecture — SIM-KK

**Status:** Production-deployed monorepo (Laravel API + Vue 3 SPA)
**Last updated:** 2026-06-14
**Production URL:** http://43.133.142.74/

---

## Overview

SIM-KK (Sistem Informasi Manajemen — Klinik Kecantikan) is a full-stack clinic management system. It handles POS transactions, patient medical records (Rekam Medis), FIFO inventory, therapist commissions, daily closing reports, and Telegram notifications.

## Repository Structure

```
sim-kk/
  apps/
    api/          Laravel 13 PHP API (port-free behind Nginx)
    web/          Vue 3 + TypeScript + Vite SPA
  docs/           Spec docs, design plans
  .workflow/      Test artifacts, blackbox/graybox results, nginx configs
  ARCHITECTURE.md This file
  AGENTS.md       Coding conventions
  CONTEXT.md      PRD / domain context
```

## Tech Stack

| Layer        | Technology                                    |
|--------------|-----------------------------------------------|
| Frontend     | Vue 3, TypeScript, Vite, Tailwind CSS 4       |
| Backend      | Laravel 13 (PHP 8.3), Sanctum auth             |
| Database     | SQLite (with FK constraints enabled)           |
| Storage      | Cloudflare R2 (clinical photos, backups)       |
| Web server   | Nginx (SPA + PHP-FPM on same server)          |
| Bot          | Telegram (webhook, reminders, aftercare)       |
| Reports      | Dompdf (PDF), PhpSpreadsheet via laravel-excel |
| CI           | GitHub Actions + Playwright smoke tests        |
| Hosting      | Tencent Cloud CVM S5.SMALL2 (43.133.142.74)   |

## API Architecture

### Authentication

- `POST /api/login` — username/password/role → Sanctum bearer token (7-day expiry).
- `POST /api/logout` — revoke current token.
- Login rate-limited to 5 attempts per minute (`throttle:5,1`).

### Authorization

All routes behind `auth:sanctum`. Role-based access enforced via `RequireRole` middleware (`role:Kasir,Terapis,Gudang,Manajer`).

- **Kasir:** POS pay, daily report submit, view own scope.
- **Terapis:** Treatment notes (own patients only via `assigned_terapis_id`), photo upload.
- **Gudang:** Inventory purchases/batches, inventory movements.
- **Manajer:** Full access — void transactions, admin CRUD, reports, audit logs, dashboard, daily report approve.

### Bootstrap Endpoint (Role-scoped)

`GET /api/bootstrap` returns the SPA's initial dataset, scoped by role:

| Role    | Patients                        | Transactions      | Services/Therapists | Inventory | Reports |
|---------|----------------------------------|--------------------|---------------------|-----------|---------|
| Manajer | Full + treatments + photos       | All                | All                 | All       | All     |
| Kasir   | Name + recordId only             | All                | All                 | No        | No      |
| Terapis | Own patients + treatments/photos | Own only           | All                 | No        | No      |
| Gudang  | None                             | None               | None                | All       | No      |

### Key Endpoints

| Method   | Path                                                  | Middleware              |
|----------|-------------------------------------------------------|--------------------------|
| POST     | `/api/transactions/pay`                               | role:Kasir,Manajer       |
| DELETE   | `/api/transactions/{transaksi}`                       | role:Manajer             |
| POST     | `/api/patients/{patient}/treatments`                  | role:Terapis,Manajer     |
| PUT/PATCH| `/api/patients/{patient}/treatments/{treatment}`      | role:Terapis,Manajer     |
| DELETE   | `/api/patients/{patient}/treatments/{treatment}`      | role:Terapis,Manajer     |
| POST     | `/api/patients/{patient}/photos`                      | role:Terapis,Manajer     |
| DELETE   | `/api/patients/{patient}/photos/{photo}`              | role:Terapis,Manajer     |
| POST     | `/api/inventory/purchases`                            | role:Gudang,Manajer      |
| DELETE   | `/api/inventory/purchases/{batch}`                    | role:Gudang,Manajer      |
| GET      | `/api/reports/{report}/export`                        | role:Manajer             |
| GET      | `/api/admin/{layanan,produk,users}[/{id}]`            | role:Manajer             |
| GET      | `/api/daily-reports/status`                           | auth:sanctum             |
| POST     | `/api/daily-reports/{tanggal}/submit`                 | role:Kasir,Manajer       |
| POST     | `/api/daily-reports/closings/{id}/approve`            | role:Manajer             |
| GET      | `/api/daily-reports/{tanggal}/export`                 | role:Manajer             |

### Idempotency

`POST /api/transactions/pay` accepts an `Idempotency-Key` header (8-128 chars, `[A-Za-z0-9._-]`). Same (user, key_hash, endpoint) returns cached response — prevents double-charge on network retry. Unique constraint enforced at DB level.

### Photo Storage

Clinical photos stored on Cloudflare R2 (`simkk-clinical` bucket). Streamed through Laravel signed URL proxy (`GET /api/photos/{photo}/raw`) — no direct R2 URL exposure.

- Upload validation: extension allowlist (png/jpg/jpeg/webp/heic), base64 size cap (14MB ≈ 10.5MB binary), magic-byte sniff via `getimagesizefromstring()`.

## Database Design

### Schema (17 tables)

```
users              → auth, role, shift, signature
pasien             → patient records (soft deletes)
terapis            → therapist master data
produk             → product categories (soft deletes)
layanan            → services/treatments (soft deletes)
batch_stok         → FIFO inventory batches (soft deletes)
pembelian_supplier → supplier purchase records (soft deletes)
transaksi          → transactions (soft deletes, surrogate PK id)
transaksi_detail   → line items (soft deletes, FK→layanan via id_produk)
buku_kas           → cash ledger entries (soft deletes)
catatan_treatment  → treatment notes (date: Y-m-d format)
foto_klinis        → clinical photo references
audit_log          → all write operations tracked
idempotency_keys   → payment replay protection (unique: user+key+endpoint)
daily_cash_float   → opening cash per day
daily_closings     → daily report submissions
stok_mutasi        → stock movement audit trail
```

### Key Constraints

- **FK constraints enabled** (`foreign_key_constraints: true` in SQLite config).
- **Unique indexes:** `idempotency_keys(user_id, key_hash, endpoint)`.
- **Soft deletes** on all critical tables — prevents accidental data loss.
- Transaction ID: `TRX-YYMMDD-NNNNN` derived from surrogate auto-increment (race-safe).

## Frontend Architecture

### Views

| View           | Component              | Role Access               |
|----------------|------------------------|---------------------------|
| Login          | `LoginView`            | Public                    |
| POS/Kasir      | `PosView`              | Kasir, Manajer            |
| Rekam Medis    | `MedicalRecordView`     | Terapis, Manajer          |
| Gudang         | `InventoryView`         | Gudang, Manajer           |
| Laporan        | `ReportsView`           | Manajer                   |
| Closing Harian | `DailyReportView`       | Kasir (submit), Manajer   |
| Dashboard      | `DashboardView`         | Manajer                   |
| Admin (CRUD)   | `AdminLayananView` etc. | Manajer                   |
| Audit Log      | `AuditLogView`          | Manajer                   |

### Design System

**Editorial Luxury** — cream/forest/champagne palette, Fraunces serif display, Inter body, JetBrains Mono for data. All CSS tokens in `apps/web/src/styles/tokens.css`.

### Real-time

30-second polling for Kasir/Manajer roles. Terapis/Gudang get manual refresh.

## Deployment

### Production (VPS)

- **Server:** Tencent Cloud CVM S5.SMALL2 (Ubuntu 24.04)
- **IP:** 43.133.142.74
- **Web server:** Nginx serving Vue SPA at `/` and proxying `/api/*` to PHP-FPM
- **Database:** SQLite at `apps/api/database/database.sqlite`
- **Scheduler:** systemd timer for daily backups + Laravel scheduler
- **R2:** `simkk-clinical` (photos) + `simkk-backups` (daily DB backups)

### Nginx Architecture

```
Browser → Nginx (port 80)
           ├── GET /          → Vue SPA (static, /var/www/sim-kk/apps/web/dist/)
           ├── GET /api/*     → PHP-FPM (/var/www/sim-kk/apps/api/public/)
           └── GET /storage/* → Static files or 404
```

### Build & Deploy

1. `cd apps/web && npm run build` → produces `dist/` (static SPA)
2. Upload `dist/` to VPS `/var/www/sim-kk/apps/web/dist/`
3. `cd apps/api && php artisan migrate` on VPS
4. `sudo systemctl reload nginx`

## Security Controls

- Sanctum token expiry: 7 days (configurable via `SANCTUM_TOKEN_EXPIRATION_MINUTES`)
- Login rate limiting: 5 attempts/minute
- Admin CRUD routes: Manajer-only middleware
- Treatment attribution: server-derived from auth user (no impersonation)
- Photo upload: extension allowlist + magic-byte validation + size cap
- CORS: domain allowlist (no wildcards)
- APP_DEBUG: `false` in production (no stack trace leakage)
- Dompdf: `isRemoteEnabled = false` (SSRF prevention)
- Patient data: role-scoped in bootstrap (Kasir sees names only, Gudang sees none)
- Password change: revokes all existing tokens

## Environment Variables

Key variables in `apps/api/.env`:

```
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=sqlite
SANCTUM_TOKEN_EXPIRATION_MINUTES=10080
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
R2_ENDPOINT=...
TELEGRAM_BOT_TOKEN=...
TELEGRAM_WEBHOOK_SECRET=...
```
