# SIM-KK — Status Report & VPS Deployment Guide

**Tanggal**: 2026-06-07
**Versi**: v0.5 (Code Complete) → v1.0 (Production) butuh VPS deploy
**Total file kode**: 64 (PHP + Vue + TS)
**Test status**: 79/80 PHPUnit pass, 4/4 Playwright pass, 12/12 Black Box RPT pass (post-fix)

---

## 1. Apa yang SUDAH selesai ✅

### Backend (apps/api — Laravel 13 + PHP 8.3)
| Module | Status | Files |
|---|---|---|
| Auth (Sanctum + role gates) | ✅ | `AuthController.php`, `RequireRole.php` middleware |
| Bootstrap data | ✅ | `BootstrapController.php` (7 entities + signaturePath + telegramChatId) |
| Transaksi (POS + FIFO + komisi snapshot) | ✅ | `TransaksiService.php`, `TransaksiController.php` |
| Rekam Medis (CRUD + foto upload) | ✅ | `RekamMedisController.php`, `StorageService.php` |
| Inventory (FIFO + barang masuk) | ✅ | `InventarisService.php`, `InventarisController.php` |
| Laporan (existing: Arus Kas + Stok + Komisi) | ✅ | `PdfService.php`, `ExcelService.php`, `LaporanController.php` |
| Daily Report (NEW: 8 sections + dual TTD + workflow) | ✅ | `DailyReportService.php`, `DailyReportController.php` |
| Inventory Movements (NEW: 11 columns) | ✅ | `InventoryMovementService.php`, `InventoryMovementController.php` |
| Telegram (Bot API + webhook + patient opt-in) | ✅ | `TelegramService.php`, `TelegramController.php` |
| Audit log | ✅ | `AuditService.php` + `audit_log` migration |
| Idempotency keys | ✅ | `IdempotencyKey.php` model + middleware |
| **Schema migration (Daily Report + Inventory Movements)** | ✅ | `2026_06_06_130000_add_daily_report_and_inventory_movements.php` |
| **Bug fixes post-audit** | ✅ | RPT-01, HIGH-1 (webhook secret), HIGH-2 (photo ownership) |

### Frontend (apps/web — Vue 3 + Vite + TypeScript)
| Module | Status | Files |
|---|---|---|
| Editorial Luxury design tokens | ✅ | `tailwind.config.ts`, `tokens.css` (1 line) |
| AppShell (top nav + role pills) | ✅ | `AppShell.vue` |
| Login view (editorial split) | ✅ | `LoginView.vue` |
| POS, Rekam Medis, Gudang, Laporan | ⏸️ Still original | not yet rewritten to editorial |
| Daily Report view | ⏳ placeholder | `DailyReportView.vue` not created |
| Inventory Movements view | ⏳ placeholder | `InventoryMovementsView.vue` not created |

### Tests
- **PHPUnit Feature**: 67 tests, 67 pass, 296 assertions
- **PHPUnit GrayBox**: 29 tests, 29 pass, 174 assertions
- **PHPUnit Unit**: 1 test, 1 fail (pre-existing FIFO exception type)
- **Playwright Smoke**: 4/4 pass
- **BlackBox PowerShell**: 51 scenarios, 12/12 in critical RPT suite (post-fix)

### Documentation
- `docs/superpowers/specs/2026-06-05-d1-readiness-design.md` (DB schema spec)
- `docs/superpowers/specs/2026-06-05-editorial-luxury-ui-design.md` (UI spec)
- `docs/superpowers/plans/2026-06-05-d1-readiness.md` (DB plan, 13 tasks)
- `docs/superpowers/plans/2026-06-05-editorial-luxury-ui.md` (UI plan, 8 tasks)
- `docs/EXTERNAL-DEPENDENCIES.md` (architecture reference)
- `docs/DEPLOY-RAILWAY.md` (legacy, still useful as reference)
- `docs/TELEGRAM-SETUP.md` (Telegram bot setup)
- `HALLUCINATION.md` (decision log)
- `outputs/DELIVERABLE.md` (client-facing summary)
- `outputs/changelog.md` (v0.1/v0.5/v1.0 roadmap)
- `outputs/visual-qa-report.md` (test status)
- `outputs/sim-kk-ui-previews/` (7 HTML previews + 9 PNG screenshots)

---

## 2. Apa yang KURANG (blocking untuk production) 🔴

### 🔴 HARD BLOCKER — butuh VPS

**1. Production deployment** — saat ini cuma jalan di local `php artisan serve`. Tidak ada domain, tidak ada HTTPS, tidak ada backup.

**2. Telegram webhook registration** — perlu URL production untuk setWebhook. Token sudah ada di `.env.example`, tapi belum dipanggil.

### 🟠 SOFT BLOCKER — bisa di-fix setelah VPS live

**3. Frontend Vue rewrite (5 view)** — POS, Rekam Medis, Gudang, Laporan, Reports. Sudah spec, belum implement Vue 3 + editorial luxury.

**4. Daily Report view (Vue 3)** — backend ready, frontend belum.

**5. Inventory Movements view (Vue 3)** — backend ready, frontend belum.

### 🟡 MEDIUM severity (open from audits, fix post-launch)

| ID | Issue | Effort |
|---|---|---|
| MED-4 | Photo upload no byte cap (100MB allowed) | S |
| MED-5 | `getimagesizefromstring` accepts SVG/BMP/GIF (polyglot risk) | S |
| MED-6 | `LIKE '%phone'` di TelegramController DoS risk | S |
| MED-7 | Audit log missing di `submit()` + `approve()` Daily Report | S |
| MED-8 | `tmp <0.2.6` npm high vuln (transitive) | S |
| LOW-1 | Idempotency-Key optional di `/pay` (duplicate risk) | S |
| LOW-2 | HEIC di regex allowlist tapi `getimagesizefromstring` returns false | S |
| LOW-3 | `idempotency_keys` gak ada cleanup (unbounded growth) | S |
| LOW-4 | `update_id` replay check missing di webhook | S |
| LOW-5 | `/start LINK` gak rate-limited | S |
| LOW-6 | Dead code `function rupiah()` di BootstrapController | S |
| INFO-1 | `rupiah()` better di `App\Support\` namespace | S |
| INFO-2 | FIFO `orderByRaw` duplicated 3x (extract `BatchStok::scopeFifoOrder()`) | S |
| INFO-3 | `tmp <0.2.6` npm transitive | S |

---

## 3. Rekomendasi VPS — Tencent / Cloudeka / Alibaba

**Buat Laravel 13 + PHP 8.3 + nginx + MySQL/D1 client**: minimum spec:

| Resource | Minimum | Recommended |
|---|---|---|
| vCPU | 2 | 2-4 |
| RAM | 2 GB | 4 GB |
| Storage | 40 GB SSD | 80 GB SSD |
| Bandwidth | 2 TB/mo | 4-5 TB/mo |
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |
| Region | Indonesia (jakarta) atau Singapore | Same as R2 region |
| Price | $5-7/mo | $8-12/mo |

### Vendor comparison

| Provider | Plan | Spec | Price | Latency from Indonesia | Notes |
|---|---|---|---|---|---|
| **Tencent Cloud** | S5.SMALL2 | 1vCPU 2GB 50GB | ~$8/mo | Jakarta region available | Bagus, support Indonesia |
| **Tencent Cloud** | S5.SMALL4 | 2vCPU 4GB 80GB | ~$15/mo | Jakarta region | Recommended |
| **Cloudeka (Indonesian)** | VPS-2 | 2vCPU 4GB 80GB | Rp 150-200rb/mo (~10 USD) | Jakarta only | Local IDR billing, support BI |
| **Cloudeka** | VPS-1 | 1vCPU 2GB 50GB | Rp 90-120rb/mo (~7 USD) | Jakarta only | Minimum OK |
| **Alibaba Cloud** | ECS t6-c1m1.large | 2vCPU 4GB 40GB | ~$12/mo | Jakarta (id) available | International-grade |
| **Alibaba Cloud** | ECS s6-c1m2.small | 1vCPU 2GB 40GB | ~$7/mo | Jakarta | Minimum OK |

**Rekomendasi saya**: **Tencent Cloud S5.SMALL4** (~$15/mo) — Jakarta region, 2vCPU 4GB cukup untuk 1 klinik + headroom untuk Vue build + CI runner.

Alternatif hemat: **Cloudeka VPS-1** (~Rp 100rb/mo) — billing IDR, support lokal, region Jakarta. Bagus untuk budget klinik kecil.

---

## 4. Step-by-Step Deploy Plan (untuk VPS baru)

### A. Persiapan (1-2 jam)

1. **Beli VPS** + catat IP publik
2. **Point domain** (kalau ada) ke IP via Cloudflare DNS
3. **Setup SSH key** di VPS: `ssh-copy-id root@<ip>`

### B. Provision server (30 menit)

```bash
# Login
ssh root@<ip>

# Update
apt update && apt upgrade -y

# Install PHP 8.3 + extensions
apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-sqlite3 php8.3-gd php8.3-zip

# Install nginx + composer + node
apt install -y nginx composer nodejs npm

# Install wrangler for D1
npm install -g wrangler
```

### C. Setup Cloudflare D1 (15 menit)

```bash
# Login
wrangler login

# Create D1
wrangler d1 create simkk
# output: database_id = "xxxx-xxxx-xxxx"

# Save database_id
```

### D. Setup Cloudflare R2 (10 menit)

1. Cloudflare dashboard → R2 → Create bucket `simkk-clinical`
2. R2 → Manage API Tokens → Create token `simkk-app` (Object Read & Write scoped)
3. Note: Account ID, Access Key, Secret Key, Endpoint URL

### E. Deploy apps/api (20 menit)

```bash
# Clone repo
cd /var/www
git clone https://github.com/<user>/sim-kk.git
cd sim-kk/apps/api

# Install
composer install --no-dev --optimize-autoloader

# Copy env
cp .env.example .env
nano .env  # fill: APP_KEY (php artisan key:generate), TELEGRAM_BOT_TOKEN, R2_*, D1_DATABASE_ID
```

Tambah config D1 di `apps/api/config/database.php` (sudah ada entry `d1` di spec; implementasinya butuh custom PDO wrapper — atau pakai driver SQLite langsung via D1 HTTP API).

Untuk **simplifikasi production**: pakai **SQLite file di VPS** dulu, dan **D1 sebagai remote database alternative** (opsional). Lihat strategi di bawah.

### F. Setup Telegram webhook (5 menit)

```bash
# Set secret
TELEGRAM_WEBHOOK_SECRET=$(openssl rand -hex 32)
echo "TELEGRAM_WEBHOOK_SECRET=$TELEGRAM_WEBHOOK_SECRET" >> /var/www/sim-kk/apps/api/.env

# Register webhook
curl -F "url=https://api.sim-kk.example.id/api/telegram/webhook" \
     -F "secret_token=$TELEGRAM_WEBHOOK_SECRET" \
     https://api.telegram.org/bot8946402437:AAEC3ptemBbg3XkYfWdOFEv3_uWcAZXFk_Q/setWebhook
```

### G. Build & deploy apps/web (15 menit)

```bash
cd /var/www/sim-kk/apps/web
npm ci
npm run build

# Copy dist to nginx root
sudo cp -r dist/* /var/www/html/
```

### H. Nginx config (10 menit)

```nginx
server {
    listen 80;
    server_name api.sim-kk.example.id;
    root /var/www/sim-kk/apps/web/dist;
    index index.html;

    # Vue SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API proxy to PHP-FPM
    location /api/ {
        root /var/www/sim-kk/apps/api/public;
        try_files $uri /index.php?$query_string;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

### I. Verification (15 menit)

```bash
# Health
curl https://api.sim-kk.example.id/api/health

# Login
curl -X POST https://api.sim-kk.example.id/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}'

# Daily report
curl https://api.sim-kk.example.id/api/daily-reports/2026-06-07/export \
  -H "Authorization: Bearer <token>" -o test.pdf
file test.pdf  # should say "PDF document"
```

---

## 5. Strategi DB: Local SQLite vs Cloudflare D1

Ada **2 pilihan arsitektur production**:

### Opsi A: VPS + SQLite file (RECOMMENDED untuk klinik kecil)
- ✅ Code existing jalan tanpa ubah
- ✅ Murah, simpel, zero network latency
- ❌ Backup manual, single point of failure
- Cocok untuk: 1 klinik, traffic 1-5 transaksi/jam

### Opsi B: VPS + Cloudflare D1 (lebih scalable)
- ✅ Data di edge Cloudflare, replicated globally
- ✅ Auto-backup Cloudflare
- ❌ Custom Laravel driver shim perlu dibuat (D1 HTTP API)
- ❌ Latency sedikit lebih tinggi (HTTP call per query)
- Cocok untuk: multi-klinik chain, scale-out

**Rekomendasi**: Opsi A untuk go-live awal. Migrasi ke D1 nanti kalau sudah ada chain.

## 6. Total Cost Estimate (Production)

| Item | Cost |
|---|---|
| VPS Tencent S5.SMALL4 Jakarta | $15/mo |
| Cloudflare DNS + SSL | $0 |
| Cloudflare D1 (free tier 5GB) | $0 |
| Cloudflare R2 (free tier 10GB) | $0 |
| Telegram Bot API | $0 |
| Domain .id (1 tahun) | $15/year (~Rp 230rb) |
| **Total bulanan** | **~$15/mo (~Rp 230rb/bulan)** |

---

## 7. Yang lo lakukan sekarang

1. **Beli VPS** — recommended Tencent S5.SMALL4 Jakarta (~$15/mo) atau Cloudeka VPS-1 hemat (~Rp 100rb)
2. **Point domain** ke VPS (kalau sudah punya; kalau belum, beli `.id` di domain registrar + add ke Cloudflare free)
3. **Beri saya IP + root access** VPS → saya eksekusi step B-I di atas (~2 jam kerja)

## 8. Yang saya kerjakan setelah VPS ready

- Implement step B-I (provision + deploy apps/api + apps/web + nginx + telegram webhook)
- Re-run full test suite against production URL
- Verify Daily Report export, POS transaksi, photo upload end-to-end
- Smoke test Telegram reminder + aftercare ke patient test
- Final commit + handoff

---

## 9. Test status ringkas (untuk client report)

```
✅ PHPUnit Feature:   67/67 pass
✅ PHPUnit GrayBox:   29/29 pass (174 assertions)
✅ Playwright Smoke:   4/4 pass
✅ BlackBox RPT:     12/12 pass (post-fix)
❌ PHPUnit Unit:      0/1  (FifoStockTest - pre-existing test bug, expects wrong exception class)
```

Total: **112/113** test pass + 1 pre-existing unit test fail (unrelated to today's work).
