# 07 — End-to-End Verification (15 min)

Setelah semua step di atas selesai, jalankan smoke test ini untuk confirm stack production-ready.

## A. Akses publik (dari laptop/HP di luar VPS)

```bash
# 1. Pages reachable
curl -I https://sim-kk.example.id
# Expected: 200, CF cache hit/miss

# 2. API health
curl https://api.sim-kk.example.id/api/health
# Expected: {"ok":true}

# 3. Login all 4 roles
for role in kasir terapis gudang manajer; do
  echo "--- $role ---"
  curl -X POST https://api.sim-kk.example.id/api/login \
    -H "Content-Type: application/json" \
    -d "{\"username\":\"$role\",\"password\":\"simkk-2026\",\"level\":\"$(tr a-z A-Z <<< ${role:0:1})${role:1}\"}" \
    -w "\nHTTP %{http_code}\n"
done
# Expected: 200 each, token in response
```

## B. Browser smoke (manual, 5 menit)

Buka https://sim-kk.example.id di browser:

1. **Login screen** load — pilih role "Kasir"
2. Login `kasir` / `simkk-2026` → masuk POS
3. Pilih layanan "Acne Calm Facial" + "Barrier Serum"
4. Pilih terapis
5. Klik **Bayar** → success, faktur muncul
6. **Logout** → switch role ke "Terapis"
7. Login `terapis` / `simkk-2026` → masuk Rekam Medis
8. Pilih pasien "Alya Maharani" → tambah catatan → save
9. Upload foto Before (PNG file dari desktop) → save → foto muncul di timeline
10. Verify foto accessible: klik foto, copy URL, paste di tab baru → foto load
11. **Logout** → switch role ke "Gudang"
12. Login `gudang` / `simkk-2026` → masuk Inventory
13. Tambah pembelian baru: qty 50, hpp 95000, expiry 2026-12-15 → save
14. Verify FIFO sort: batch baru muncul di posisi yang benar
15. **Logout** → switch role ke "Manajer"
16. Login `manajer` / `simkk-2026` → masuk Reports
17. Klik **Export PDF** (Laporan Arus Kas) → file download, buka di PDF viewer → ada data
18. Klik **Export XLSX** (Laporan Stok) → file download, buka di Excel → ada data
19. Klik **Export XLSX** (Komisi Terapis) → file download → ada nama + nominal

## C. Security smoke (opsional, 5 menit)

```bash
# 1. Login 500 fix: missing level field
curl -X POST https://api.sim-kk.example.id/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"kasir","password":"simkk-2026"}' -w "\nHTTP %{http_code}\n"
# Expected: 422 (was 500 before F-002 fix)

# 2. Photo upload extension filter
echo "test" > /tmp/shell.php
curl -X POST https://api.sim-kk.example.id/api/patients/1/photos \
  -H "Authorization: Bearer $TERAPIS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"label":"Before","filename":"shell.php","content":"test"}' -w "\nHTTP %{http_code}\n"
# Expected: 422 (was 201 before F-001 fix)

# 3. Role gate: kasir → /pay (allowed) → /reports (forbidden)
KASIR_TOKEN=$(curl -X POST https://api.sim-kk.example.id/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' | jq -r .token)

# Should succeed
curl -X POST https://api.sim-kk.example.id/api/transactions/pay \
  -H "Authorization: Bearer $KASIR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}]}' \
  -w "\nHTTP %{http_code}\n"
# Expected: 200

# Should fail
curl https://api.sim-kk.example.id/api/reports/finance/export \
  -H "Authorization: Bearer $KASIR_TOKEN" -w "\nHTTP %{http_code}\n"
# Expected: 403

# 4. Terapis impersonation fix: include "terapis" field → 422
TERAPIS_TOKEN=$(curl -X POST https://api.sim-kk.example.id/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"terapis","password":"simkk-2026","level":"Terapis"}' | jq -r .token)

curl -X POST https://api.sim-kk.example.id/api/patients/1/treatments \
  -H "Authorization: Bearer $TERAPIS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"terapis":"Fake Name","judul":"x","catatan":"y"}' -w "\nHTTP %{http_code}\n"
# Expected: 422 "terapis field is prohibited"

# 5. WAF rate limit trigger
for i in {1..10}; do
  curl -X POST https://api.sim-kk.example.id/api/login \
    -H "Content-Type: application/json" \
    -d '{"username":"x","password":"x"}' -o /dev/null -w "%{http_code} "
done
echo
# Expected: first 5 = 401, rest = 403
```

## D. Backup + monitoring (opsional tapi recommended)

### Backup harian ke R2

Tambahkan ke crontab VPS:
```bash
# SSH ke VPS
crontab -e
# Tambah line:
0 2 * * * /home/simkk/sim-kk/deploy/cloudflare/backup.sh >> /var/log/simkk-backup.log 2>&1
```

Script `backup.sh`:
```bash
#!/usr/bin/env bash
set -euo pipefail
TS=$(date +%Y%m%d-%H%M%S)
DB="/home/simkk/sim-kk/apps/api/database/database.sqlite"
aws s3 cp "$DB" s3://simkk-backups/db-$TS.sqlite \
  --endpoint-url "$R2_ENDPOINT" \
  || { echo "Backup failed at $TS" | mail -s "SIM-KK backup FAIL" admin@sim-kk.example.id; exit 1; }
# Retention: keep 30 days
aws s3 ls s3://simkk-backups/ --endpoint-url "$R2_ENDPOINT" | \
  awk '{print $4}' | sort | head -n -30 | \
  xargs -I {} aws s3 rm s3://simkk-backups/{} --endpoint-url "$R2_ENDPOINT" || true
```

### Uptime monitoring (free)

- https://uptimerobot.com/ (free 50 monitors)
- Add monitor: `https://api.sim-kk.example.id/api/health` — check every 5 min
- Alert: email/Slack kalau down

### Error tracking (opsional)

- Sentry: `composer require sentry/sentry-laravel` (kalau mau production-grade)
- Self-host: skip untuk sekarang

## Checklist Final

- [ ] Pages URL load tanpa error
- [ ] Login 4 role bekerja
- [ ] Kasir bisa POS pay
- [ ] Terapis bisa add treatment + upload foto
- [ ] Foto accessible via R2 URL
- [ ] Gudang bisa add purchase
- [ ] Manajer bisa export PDF + 2 XLSX
- [ ] WAF rate limit trigger
- [ ] Backup cron jalan
- [ ] UptimeRobot monitor aktif
- [ ] `.env` di VPS sudah production values (APP_DEBUG=false, APP_ENV=production)
- [ ] APP_KEY di-generate dan aman
- [ ] Database di-backup ke R2

## Out-of-Scope (untuk next iteration)

- Email notifications (kirim invoice ke pasien) → perlu SMTP service
- WhatsApp Business integration → perlu Meta Business verification
- Custom domain (kalau belum beli)
- CI/CD auto-deploy (push ke GitHub → auto deploy ke VPS via GitHub Actions)
- Multi-user concurrent stress test
