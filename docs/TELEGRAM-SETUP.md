# Telegram Bot Setup — SIM-KK

Panduan ini menjelaskan cara setup bot Telegram untuk notifikasi pasien SIM-KK (reminder appointment + aftercare treatment).

## 1. Buat Bot via @BotFather

1. Buka Telegram, cari `@BotFather`
2. Kirim `/newbot`
3. Ikuti instruksi: kasih nama bot (mis. `SIM-KK Klinik Samarinda`) dan username (mis. `sim_kk_klinik_bot`)
4. **Copy token** yang diberikan — masukkan ke `apps/api/.env`:
   ```
   TELEGRAM_BOT_TOKEN=8946402437:AAEC3ptemBbg3XkYfWdOFEv3_uWcAZXFk_Q
   ```
5. Simpan username bot untuk dipampang di UI klinik sebagai QR code / link `t.me/sim_kk_klinik_bot`

## 2. Set Webhook (setelah deploy)

Bot Telegram butuh webhook URL agar server SIM-KK bisa menerima pesan dari pasien (untuk `/start` opt-in flow).

**Production (VPS):**
```bash
curl -F "url=https://api.sim-kk.example.id/api/telegram/webhook" \
     https://api.telegram.org/bot8946402437:AAEC3ptemBbg3XkYfWdOFEv3_uWcAZXFk_Q/setWebhook
```

**Local dev (testing via ngrok):**
```bash
# Terminal 1 — jalankan ngrok
ngrok http 8000

# Terminal 2 — set webhook ke URL ngrok
curl -F "url=https://YOUR-NGROK-ID.ngrok.io/api/telegram/webhook" \
     https://api.telegram.org/bot8946402437:AAEC3ptemBbg3XkYfWdOFEv3_uWcAZXFk_Q/setWebhook
```

**Cek status webhook:**
```bash
curl https://api.telegram.org/bot8946402437:AAEC3ptemBbg3XkYfWdOFEv3_uWcAZXFk_Q/getWebhookInfo
```

## 3. (Production) Webhook Secret

Tambahkan secret token untuk validasi request dari Telegram:

```bash
# Generate random secret
openssl rand -hex 32
# Output: 9f3a2b1c4d5e...

# Set di .env
TELEGRAM_WEBHOOK_SECRET=9f3a2b1c4d5e...

# Register webhook dengan secret
curl -F "url=https://api.sim-kk.example.id/api/telegram/webhook" \
     -F "secret_token=9f3a2b1c4d5e..." \
     https://api.telegram.org/bot8946402437:AAEC3ptemBbg3XkYfWdOFEv3_uWcAZXFk_Q/setWebhook
```

Server akan validate `X-Telegram-Bot-Api-Secret-Token` header. (Saat ini validation belum implemented — TODO.)

## 4. Patient Opt-in Flow

Pasien harus start chat dengan bot dulu sebelum bisa terima notifikasi.

**Cara 1 — Direct link dengan RM-id (recommended):**
```
t.me/sim_kk_klinik_bot?start=RM-0001
```
(di-trigger dari aplikasi saat Kasir input nomor telepon, pasien klik link di WhatsApp/SMS)

**Cara 2 — Manual /start:**
1. Pasien buka bot, kirim `/start`
2. Bot balas welcome message dengan instruksi
3. Pasien kirim `/start LINK RM-0001` (ganti dengan RM-id mereka)
4. Bot confirm: "Anda terhubung sebagai [Nama Pasien]"

**Cara 3 — Share kontak:**
1. Pasien share contact via bot
2. Backend normalize nomor (08xxx → 628xxx), match ke `pasien.nomor_telp` via `LIKE '%last-9-digits'`
3. Jika match → auto-link, simpan `telegram_chat_id`

## 5. Test Flow (Local)

**Start bot di Telegram:**
- Buka `t.me/sim_kk_klinik_bot` (ganti dengan username bot lo)
- Kirim `/start`
- Harusnya dapat welcome message

**Kirim reminder (via API):**
```bash
# Login dulu untuk dapat token
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}'

# Kirim reminder
curl -X POST http://127.0.0.1:8000/api/telegram/reminder \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"pasien_id": 1, "when": "2026-06-10 14:00"}'
```

Pesan harus sampai ke Telegram pasien.

**Kirim aftercare (perlu login sebagai Terapis):**
```bash
curl -X POST http://127.0.0.1:8000/api/telegram/aftercare \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"pasien_id": 1, "treatment": "Facial Basic"}'
```

## 6. Log & Debug

Check apakah bot firing:
```bash
tail -f apps/api/storage/logs/laravel.log | grep -i telegram
```

Check webhook info:
```bash
curl https://api.telegram.org/bot8946402437:AAEC3ptemBbg3XkYfWdOFEv3_uWcAZXFk_Q/getWebhookInfo
```

## 7. Common Issues

| Issue | Fix |
|---|---|
| Bot tidak respond | Cek `TELEGRAM_BOT_TOKEN` di env, restart `php artisan serve` |
| Webhook not set | Run `setWebhook` command di step 2 |
| `telegram_chat_id` masih NULL | Pasien belum `/start` bot, atau share kontak belum berhasil |
| Error 401 unauthorized | Token salah atau expired |
| Error 400 chat not found | `telegram_chat_id` invalid atau pasien belum start bot |

## 8. Cron / Scheduler (TODO)

Saat ini reminder dikirim manual via API. Production harusnya:
- Laravel scheduler (`app/Console/Kernel.php`) dengan command `telegram:reminder` yang jalan tiap jam
- Scan `transaksi` table untuk appointment besok, kirim reminder otomatis
- Scan `transaksi` yang baru jadi Lunas, kirim aftercare 1 jam setelah treatment

Schedule: `app/Console/Kernel.php`
```php
$schedule->command('telegram:reminder')->hourly();
```

## 9. Test Status

- ✅ `TelegramServiceTest`: 5/5 pass
- ✅ `TelegramWebhookTest`: 8/8 pass
- ⏳ Production cron: TODO
- ⏳ Webhook secret validation: TODO
- ⏳ Patient UX (UI tombol "Kirim Reminder" di POS): TODO
