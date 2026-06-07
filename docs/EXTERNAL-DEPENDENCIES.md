# External Dependencies — SIM-KK

Source evidence: `Rancangan Sistem Informasi Klinik Kecantikan.pdf` (DPPL)

Dokumen ini mencatat semua API eksternal, layanan, dan infrastruktur yang dibutuhkan untuk menjalankan fitur-fitur yang disebutkan di PRD. Setiap dependency dipetakan ke fitur asalnya.

---

## Pemetaan Fitur PRD → Dependencies

| Fitur PRD | Dependencies yang Dibutuhkan | Status |
|---|---|---|
| Login (role-based: Kasir, Terapis, Gudang, Manajer) | Laravel Auth + Sanctum 4.x | ✅ implemented (`apps/api`) |
| Rekam Medis (CRUD keluhan, tindakan, foto) | S3-compatible Storage (foto before/after) | ✅ code ready, `STORAGE_DISK=r2` flips to Cloudflare R2 |
| POS & Kasir (transaksi, komisi, faktur) | — (logic internal) | ✅ Laravel `TransaksiService` w/ FIFO + komisi snapshot |
| Inventaris (FIFO, HPP, barang masuk) | — (logic internal) | ✅ FIFO mutation in `TransaksiService::decrementStock` |
| Laporan PDF (arus kas, laba/rugi) | DomPDF 3.1 | ✅ `app/Services/PdfService.php` + Blade view |
| Laporan Excel (stok, komisi terapis) | Maatwebsite/Excel 3.1 (PhpSpreadsheet) | ✅ `app/Services/ExcelService.php` |
| **Telegram** (notifikasi pasien) | **Telegram Bot API** via raw HTTP (Laravel `Http` facade) | ✅ `TelegramService` + 2 routes (`/telegram/reminder`, `/aftercare`); needs `TELEGRAM_BOT_TOKEN` |

---

## External APIs

### 1. Telegram Bot API (PILIHAN FINAL — replaces planned WhatsApp integration)

**Fitur terkait:** Notifikasi pasien untuk reminder appointment & aftercare treatment (PRD: `Nomor_telp` — *"Digunakan untuk integrasi WhatsApp"*; di-reinterpret sebagai Telegram karena klien tidak memiliki Meta Business API)

- **Endpoint:** `https://api.telegram.org/bot<token>/sendMessage`
- **Fungsi:** Kirim notifikasi teks ke pasien via Telegram bot — reminder jadwal treatment, aftercare instructions
- **Auth:** Bot token dari `@BotFather` di Telegram
- **Pricing:** Gratis (no per-message cost unlike WhatsApp Business API)
- **Dokumentasi:** https://core.telegram.org/bots/api
- **Patient opt-in flow:** Pasien start bot klinik, dapat `chat_id` → simpan di `pasien.telegram_chat_id` (migration `2026_06_06_120000_add_telegram_chat_id_to_pasien`)
- **Kompleksitas:** Low
- **Kebutuhan:** Bot token Telegram, pasien opt-in via `/start`

**Use cases yang diimplement**:
1. **Treatment Reminder** (`POST /api/telegram/reminder`, role: Manajer+Kasir+Terapis)
   - Trigger: manual atau cron (TODO scheduler) sebelum appointment
   - Payload: `pasien_id`, `when`
   - Template: "Halo [Nama], ini pengingat jadwal treatment Anda: [tanggal] jam [X]"
2. **Aftercare Tips** (`POST /api/telegram/aftercare`, role: Manajer+Terapis)
   - Trigger: setelah treatment selesai
   - Payload: `pasien_id`, `treatment`
   - Template: "Setelah [treatment], berikut tips aftercare: …"

**Status:** ✅ Code ready. Tested (5/5 PHPUnit pass). Bot token belum di-set.

**Catatan migrasi**:
- Sebelumnya ada `netflie/whatsapp-cloud-api` di composer.json (sudah dihapus)
- File `WhatsAppService.php` dan `WhatsAppController.php` dihapus
- `config/sim-kk.php` `whatsapp` section diganti `telegram` (cuma `bot_token` env var)
- Routes: `whatsapp/reminder` & `whatsapp/aftercare` → `telegram/reminder` & `telegram/aftercare`
- Migration `2026_06_06_120000_add_telegram_chat_id_to_pasien` menambahkan kolom `telegram_chat_id` ke tabel `pasien`

### 2. S3-Compatible Object Storage API (unchanged)

**Fitur terkait:** Upload foto klinis Before/After

- **Fungsi:** Upload, serve, dan manage foto before/after klinis secara terpisah dari database
- **Auth:** IAM Access Key + Secret Key (atau API token per provider)
- **Opsi provider (belum dipilih):**

| Provider | Harga/bulan | Free Tier | Catatan |
|---|---|---|---|
| Cloudflare R2 | $0.015/GB | 10GB + egress gratis | S3-compatible, paling murah |
| AWS S3 Jakarta | $0.023/GB | 5GB (12 bulan) | Standard, banyak dokumentasi |
| Backblaze B2 | $0.006/GB | 10GB | Sangat murah, S3-compatible |
| DigitalOcean Spaces | $5/bulan | 250GB include | Simple pricing |

- **Kompleksitas:** Medium
- **Catatan:** Private bucket + signed URLs untuk foto klinis. Jangan simpan foto langsung di database.

**Status:** ⚠️ Provider belum dipilih. Rekomendasi: Cloudflare R2

---

## Platform & Infrastruktur (LOCKED)

### 3. Server Hosting — VPS ✅

**Status:** Decided. VPS dengan PHP 8.3 + nginx + php-fpm. Laravel app stay on VPS (per `deploy/cloudflare/02-vps-provision.md`).

| Service | Root dir | Build | Start |
|---|---|---|---|
| API (Laravel) | `apps/api` | `composer install --no-dev` | `php artisan serve` (or php-fpm) |
| Web (Vue 3) | `apps/web` | `npm ci && npm run build` | `npx serve dist -s` |

### 4. Database Hosting — Cloudflare D1 ✅

**Status:** Decided. D1 SQLite at edge, accessed from VPS via Cloudflare API. D1 = `simkk` database.

Local dev: SQLite file. Production: D1 via `wrangler d1` migration.

- **Setup**: `wrangler d1 create simkk` → dapat `database_id` → update `wrangler.toml` → `wrangler d1 migrations apply simkk --remote`
- **Cost**: Free tier 5GB storage + 5M reads/day + 100K writes/day — lebih dari cukup untuk 1 klinik Samarinda (1-5 transaksi/jam)
- **Prerequisites**: D1 driver shim in `apps/api/config/database.php` (D1 SQLite wire protocol)

### 5. Object Storage — Cloudflare R2 ✅

**Status:** Decided. R2 bucket `simkk-clinical` untuk foto Before/After klinis. S3-compatible.

- **Setup**: Cloudflare dashboard → R2 → Create bucket → Create API token scoped to bucket
- **Env vars**: `STORAGE_DISK=r2`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET=simkk-clinical`, `R2_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com`
- **Cost**: $0.015/GB stored, **egress free** (vs AWS S3 yang charge egress)
- **Use case**: foto before/after upload, served via signed URLs atau R2 public domain

### 6. Domain + DNS + SSL — Cloudflare (pending klien) ⚠️

**Status:** Pending decision klien. Cloudflare (free tier) di-rekomendasikan.

- Cloudflare DNS + SSL + DDoS protection = gratis
- Domain `.id` ~$15/tahun atau `.com` ~$10/tahun
- Perlu klien konfirmasi: sudah punya domain atau perlu beli baru?

---

## Packages Laravel (ter-install)

| Package | Env Variable | Fungsi | Status |
|---|---|---|---|
| `laravel/sanctum` | `SANCTUM_STATEFUL_DOMAINS` | API bearer auth | ✅ installed 4.x |
| `aws/aws-sdk-php` | `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT` | R2 S3-compatible storage | ✅ installed, `r2` disk configured |
| `dompdf/dompdf` | — | Laporan PDF (arus kas, laba/rugi) | ✅ installed 3.1 |
| `maatwebsite/excel` | — | Laporan XLSX (stok, komisi) | ✅ installed 3.1 |
| `league/flysystem-aws-s3-v3` | — | S3/R2 driver for Storage facade | ✅ installed |

**Removed**: `netflie/whatsapp-cloud-api` (ganti Telegram).

---

## Estimasi Biaya Bulanan (LOCKED: Tencent CVM + SQLite + R2 + Telegram)

| Komponen | Harga/bulan |
|---|---|
| Tencent CVM S5.SMALL2 (2vCPU 2GB 40GB) | **Rp 60.000** (~$4) |
| Cloudflare DNS + SSL | $0 |
| Cloudflare R2 (10 GB free) | $0 |
| Telegram Bot API | $0 |
| Domain (.id/.com) | ~Rp 230.000/tahun |
| **Total production** | **~Rp 60.000/bulan + Rp 230rb/tahun** |

Lebih murah dari estimasi sebelumnya:
- WA Business API (per conversation billing) → Telegram (gratis)
- Local SQLite (zero ops) → D1 nanti kalau scale
- R2 egress gratis (vs AWS S3)

---

## Infrastruktur Diagram

```
┌─────────────────────────────────────────────────────┐
│                    CLOUDFLARE                        │
│         (DNS + CDN + SSL + DDoS Protection)          │
│         (R2: simkk-clinical bucket — foto klinis)   │
└──────────────────────┬──────────────────────────────┘
                       │ HTTPS (port 443)
                       ▼
┌─────────────────────────────────────────────────────┐
│  Tencent CVM S5.SMALL2 — Jakarta (~Rp 60rb/bulan)   │
│  Ubuntu 24.04 LTS + nginx 1.24 + PHP 8.3-FPM        │
│  ├─ /api/* → PHP-FPM → Laravel 13                    │
│  ├─ /*     → /var/www/sim-kk/apps/web/dist (Vue 3) │
│  ├─ SQLite (local file, daily backup to R2)         │
│  └─ systemd: php8.3-fpm + sim-kk-scheduler          │
└──────────────────────┬──────────────────────────────┘
                       │
                       ▼
              ┌─────────────────┐
              │  Telegram Bot API │
              │  (free, no Meta)  │
              └─────────────────┘
```

---

## Unknowns (Perlu Keputusan Klien)

- ⚠️ Apakah klinik punya domain sendiri atau perlu beli baru
- ⚠️ Volume foto klinis per bulan (menentukan storage cost)
- ⚠️ Data retention policy untuk foto klinis (berapa lama disimpan)
- ⚠️ S3 provider yang dipilih (R2, AWS S3, atau lainnya)
- ⚠️ Konfirmasi bot Telegram klinik sudah dibuat via @BotFather (token belum di-set di env)

---

## Migration Timeline (2026-06-06)

- **Removed**: `netflie/whatsapp-cloud-api` composer dep, `app/Services/WhatsAppService.php`, `app/Http/Controllers/Api/WhatsAppController.php`, `whatsapp` config section, `whatsapp/*` routes, `WHATSAPP_*` env vars
- **Added**: `app/Services/TelegramService.php`, `app/Http/Controllers/Api/TelegramController.php`, `telegram` config section, `telegram/*` routes, `TELEGRAM_BOT_TOKEN` env var, migration `2026_06_06_120000_add_telegram_chat_id_to_pasien`
- **Updated**: `BootstrapController` now exposes `telegramChatId` per pasien
- **Tested**: 13/13 PHPUnit TelegramServiceTest + TelegramWebhookTest pass
- **Updated platform**: Railway + PostgreSQL replaced with **VPS + Cloudflare D1** (sesuai spec 2026-06-05 D1-Readiness)
- **Updated storage**: PostgreSQL replaced with **Cloudflare D1**; foto klinis tetap di **Cloudflare R2** (sudah configured)
