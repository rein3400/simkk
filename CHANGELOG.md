# Changelog — SIM-KK

All notable changes to this project are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [Unreleased] — 2026-06-14

### Fixed (user-reported bugs)
- **TTD "Mira Santoso" di laporan harian PDF** — `Mira Santoso` adalah nama placeholder dari `UserSeeder` yang nyangkut di production karena `ProductionBootstrapSeeder` pakai `firstOrCreate(['username'=>'manajer'])` yang tidak update nama existing. Cuma ada 1 user Manajer di sistem → otomatis jadi default approver → namanya muncul di TTD PDF.
  - `apps/api/database/seeders/UserSeeder.php` — ganti placeholder `Mira Santoso` → `Manajer Klinik` (generic, jelas sebagai placeholder).
  - `apps/api/resources/views/reports/daily.blade.php` — TTD fallback: kalo closing **belum** di-approve → tampil `(Belum disetujui)` abu-abu, bukan underscore kosong. Kalo approved → nama asli approver dari DB.
  - Production DB patched: row `manajer` di-rename ke `Manajer Klinik`. Rename via UI Admin → User juga bisa (UserAdminController `update` endpoint support `nama_lengkap`).
- **Bug Audit Log** — `apps/web/src/services/api.ts:312` `getAuditLogs()` dulu expect `AuditLogEntry[]`, tapi backend `AuditLogController::index` return `{count, rows: [...]}`. Plus field name beda: backend `nama_lengkap`/`username`, frontend `user_name`. Sekarang dinormalisasi di service layer.
- **Bug Pasien (MedicalRecordView)** — `apps/web/src/views/MedicalRecordView.vue`
  - `selectedPatientId` stuck ke initial patient list (gak reactive). Tambah `watch` di `props.patients` → fallback ke pasien pertama kalo ID gak valid lagi setelah refresh.
  - Dropdown label cuma nama → sekarang `Nama · RM-XXX`.
  - Sidebar tambah info: jumlah treatment & foto klinis.
  - Empty state: "Belum ada pasien terdaftar" kalo list kosong.

### Added
- **Deploy endpoint HTTP** — `apps/api/app/Http/Controllers/Api/DeployController.php` + route `POST /api/admin/deploy` (Manajer only + `X-Deploy-Secret` header). Backup SSH port 22 yang sering di-block firewall kantor/client.
- Deploy scripts: `.workflow/deploy.ps1`, `.workflow/phase1-deploy-setup.sh`, `.workflow/webconsole-deploy.sh`.

### Changed
- `apps/api/routes/api.php` — register `POST /api/admin/deploy` (di dalam `auth:sanctum` + `role:Manajer` group).

### Verification (live VPS, post-deploy)
- `/api/health` → 200
- `/api/login` (manajer) → token issued, `user.nama_lengkap = Manajer Klinik`
- `/api/audit-logs` → `{count, rows}` shape with `nama_lengkap` field correct
- `/api/bootstrap` → 6 patients, user `manajer` name = `Manajer Klinik`
- `/api/daily-reports/2026-06-14/export` → 27 KB valid PDF, 3 pages
- SPA `/` → 200, JS bundle 177 KB
- Tests: 84/85 pass (1 pre-existing `FifoStockTest` failure unrelated — `ValidationException` vs `RuntimeException`).
- `vue-tsc --noEmit` clean.

### Rollback
VPS retains backups at:
- `/var/www/sim-kk/apps/web/dist.bak`
- `/var/www/sim-kk/apps/api/app.bak`
- `/var/www/sim-kk/apps/api/database.bak`
- `/var/www/sim-kk/apps/api/routes.bak`
- `/var/www/sim-kk/apps/api/resources.bak`

---

## Session-2026-06-14 deploy

1. Build SPA: `npm run build` di `apps/web/` → `dist/` (vue-tsc clean, vite built in 2.06s).
2. Build deploy archive `simkk-deploy.tar.gz` (207 KB) — `app/`, `config/`, `database/`, `routes/`, `resources/`, `composer.*`, `artisan`, `dist/`.
3. SCP ke VPS `/tmp/simkk-deploy.tar.gz`.
4. Backup existing folders di VPS.
5. Extract ke `/tmp/simkk-stage/`, copy ke `/var/www/sim-kk/apps/`.
6. `php artisan config:clear`, `route:clear`, `view:clear`, `cache:clear` → re-cache `route:cache` + `view:cache`.
7. `systemctl reload nginx`.
8. Patch DB: rename user `manajer` ke `Manajer Klinik`.
9. Smoke test 5 endpoint + verify dist served new build.

---

## Recent commits
```
a3f6f51 feat: comprehensive P0/P1/P2 fixes + role-scoping + soft deletes + E2E suite
5b80344 fix(views): 4 admin views now render properly (was: blank page)
88a8380 fix(photos): serve R2 images through Laravel proxy
6e3703c fix(styling): restore Editorial Luxury CSS for SPA content views
c3cfcd8 fix(web): translate payTransaction payload to snake_case
```
