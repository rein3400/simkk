# SIM-KK — VPS Deployment Guide

Deploy SIM-KK ke Ubuntu 24.04 VPS (Tencent CVM S5.SMALL2 atau setara).
Tested on Tencent CVM S5.SMALL2, 2 vCPU / 2 GB RAM, Ubuntu 24.04 LTS.

> **One-line summary:** Connect VPS → git pull → migrate → seed → build SPA → reload nginx.
> Initial setup ~15 menit, update ~3 menit.

**VPS info:**
- Host: `43.133.142.74` (Tencent Cloud CVM S5.SMALL2)
- User: `ubuntu`
- Password: `3M6-R9q-Uki-R3c`
- Repo: https://github.com/rein3400/simkk (branch `main`)
- Local app: `/var/www/sim-kk`

---

## 1. Persiapan Server (sekali seumur hidup, kalau VPS masih polos)

### 1.1 Login + firewall
```bash
ssh ubuntu@43.133.142.74
# Password: 3M6-R9q-Uki-R3c
```

```bash
sudo apt update && sudo apt upgrade -y
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

### 1.2 Install runtime stack
```bash
# PHP 8.3 + extensions (gd, zip, sqlite wajib)
sudo DEBIAN_FRONTEND=noninteractive apt install -y \
  nginx php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-sqlite3 php8.3-bcmath \
  php8.3-intl php8.3-gd

# Composer
curl -sS https://getcomposer.org/installer | sudo php -- \
  --install-dir=/usr/local/bin --filename=composer

# Node 20 LTS (untuk build SPA)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Verify
php -v
composer --version
node -v
nginx -v
```

### 1.3 Clone repo + permissions
```bash
sudo mkdir -p /var/www
sudo chown ubuntu:ubuntu /var/www
cd /var/www
git clone https://github.com/rein3400/simkk.git sim-kk
cd sim-kk
git checkout main
```

### 1.4 Konfigurasi API (.env + permissions)
```bash
cd /var/www/sim-kk/apps/api
KEY="base64:$(openssl rand -base64 32)"
cat > .env <<EOF
APP_NAME=SIM-KK
APP_ENV=production
APP_KEY=${KEY}
APP_DEBUG=false
APP_URL=http://43.133.142.74

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/sim-kk/apps/api/database/database.sqlite

SESSION_DRIVER=file
SESSION_LIFETIME=120

CACHE_STORE=file
QUEUE_CONNECTION=sync

BROADCAST_DRIVER=log
FILESYSTEM_DISK=public

SANCTUM_STATEFUL_DOMAINS=43.133.142.74,localhost
EOF

# Composer install + migrate + storage
composer install --no-dev --optimize-autoloader
touch database/database.sqlite
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link

# Permissions (WAJIB — tanpa ini SQLite + storage log Permission denied)
sudo chown -R www-data:www-data /var/www/sim-kk/apps/api/storage
sudo chown -R www-data:www-data /var/www/sim-kk/apps/api/bootstrap/cache
sudo chown -R www-data:www-data /var/www/sim-kk/apps/api/database
sudo chmod -R 775 /var/www/sim-kk/apps/api/storage /var/www/sim-kk/apps/api/bootstrap/cache
sudo chmod 664 /var/www/sim-kk/apps/api/database/database.sqlite
```

### 1.5 Build frontend
```bash
cd /var/www/sim-kk/apps/web
npm ci
npm run build
```

### 1.6 Nginx config
```bash
sudo tee /etc/nginx/sites-available/simkk > /dev/null <<'NGX'
server {
    listen 80 default_server;
    server_name _;
    root /var/www/sim-kk/apps/api/public;
    index index.php index.html;

    # SPA — semua non-API request fallback ke /index.html
    location / {
        root /var/www/sim-kk/apps/web/dist;
        try_files $uri $uri/ /index.html;
    }

    # API — Laravel
    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        root /var/www/sim-kk/apps/api/public;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    # Storage uploads
    location /storage/ {
        alias /var/www/sim-kk/apps/api/storage/app/public/;
        try_files $uri $uri/ =404;
    }

    error_log /var/log/nginx/simkk-error.log;
    access_log /var/log/nginx/simkk-access.log;
}
NGX

sudo ln -sf /etc/nginx/sites-available/simkk /etc/nginx/sites-enabled/simkk
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

### 1.7 Enable services
```bash
sudo systemctl enable nginx php8.3-fpm
sudo systemctl restart nginx php8.3-fpm
```

### 1.8 Seed data (users + sample)
```bash
cd /var/www/sim-kk/apps/api
php artisan db:seed --class=UserSeeder --force
php artisan db:seed --class=SupplierSeeder --force
php artisan db:seed --class=BookingSeeder --force
```

UserSeeder insert (idempotent — firstOrCreate):
- `manajer` (Manajer Klinik) / `simkk-2026`
- `kasir` (Nadia Putri) / `simkk-2026`
- `terapis` (dr. Melati) / `simkk-2026`
- `gudang` (Raka Pramana) / `simkk-2026`
- `admin` (Admin Sistem) / `simkk-2026` ← untuk R10/R11

---

## 2. Update Workflow (kode baru dari GitHub)

Setiap kali ada perubahan di local + push ke `rein3400/simkk` main, VPS pull dengan script di bawah. Total ~2-3 menit.

**Single block (copy-paste ke Web Console VNC):**
```bash
cd /var/www/sim-kk && git pull origin main && cd apps/api && \
  composer dump-autoload && \
  php artisan config:clear && \
  php artisan route:clear && \
  php artisan cache:clear && \
  php artisan migrate --force && \
  php artisan storage:link 2>&1 | tail -1 && \
  php artisan config:cache && \
  php artisan route:cache && \
  cd ../web && \
  npm ci && \
  npm run build && \
  cd .. && \
  sudo systemctl reload nginx && \
  echo "=== UPDATED ==="
```

**Step-by-step (kalau satu gagal, lo bisa stop di tengah):**

1. Pull kode baru:
   ```bash
   cd /var/www/sim-kk
   git pull origin main
   ```
   Expected: lists changed files, ends with "Fast-forward" or "Already up to date".

2. Backend — clear cache + migrate:
   ```bash
   cd apps/api
   composer dump-autoload
   php artisan config:clear
   php artisan route:clear
   php artisan cache:clear
   php artisan migrate --force
   php artisan storage:link 2>&1 | tail -1
   php artisan config:cache
   php artisan route:cache
   ```

3. Frontend — rebuild:
   ```bash
   cd ../web
   npm ci
   npm run build
   ```

4. Reload web server:
   ```bash
   cd ..
   sudo systemctl reload nginx
   ```

5. Re-seed kalau ada seeder baru:
   ```bash
   cd apps/api
   php artisan db:seed --class=SupplierSeeder --force
   php artisan db:seed --class=BookingSeeder --force
   ```

---

## 3. Verifikasi cepat (3 menit smoke test)

```bash
# Frontend HTML
curl -sI http://127.0.0.1/ | head -3
# Expected: HTTP/1.1 200 OK

# API login
curl -s -X POST http://127.0.0.1/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"username":"manajer","password":"simkk-2026","level":"Manajer"}' | head -c 200
# Expected: {"token":"...","user":{...}}

# Dashboard
TOKEN=$(curl -s -X POST http://127.0.0.1/api/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"username":"manajer","password":"simkk-2026","level":"Manajer"}' | node -e "let d='';process.stdin.on('data',c=>d+=c).on('end',()=>console.log(JSON.parse(d).token))")
curl -s http://127.0.0.1/api/dashboard -H "Authorization: Bearer $TOKEN" | \
  node -e "let d='';process.stdin.on('data',c=>d+=c).on('end',()=>{const j=JSON.parse(d); console.log('upcoming:',(j.upcoming_bookings||[]).length,'| digest:',(j.digest_klien||[]).length)})"
# Expected: upcoming: 5 | digest: 5

# Suppliers
curl -s http://127.0.0.1/api/suppliers -H "Authorization: Bearer $TOKEN" | head -c 200
# Expected: list of 5-6 suppliers
```

Buka **http://43.133.142.74/** di browser → login sebagai `manajer` → dashboard shows 4 stats + Jadwal booking + Digest klien.

---

## 4. Default Users

| Username | Password   | Role     | Modul akses |
|----------|------------|----------|-------------|
| `manajer` | `simkk-2026` | Manajer  | Semua (Kasir, Rekam Medis, Gudang, Laporan, Closing, Dashboard, Layanan, Produk, User, Audit) |
| `kasir`   | `simkk-2026` | Kasir    | Kasir POS + Closing Harian |
| `terapis` | `simkk-2026` | Terapis  | Rekam Medis (pasien assigned) + buat treatment + upload foto |
| `gudang`  | `simkk-2026` | Gudang   | Gudang FIFO + Barang masuk (dropdown supplier) |
| `admin`   | `simkk-2026` | Admin   | Sama seperti Manajer (legacy compatibility) |

---

## 5. Rollback (kalau update rusak)

```bash
cd /var/www/sim-kk
# Lihat 5 commit terakhir
git log --oneline -5
# Rollback ke commit sebelumnya
git reset --hard HEAD~1
# Re-run update steps
cd apps/api && composer dump-autoload && php artisan config:cache && php artisan route:cache
cd ../web && npm ci && npm run build
cd .. && sudo systemctl reload nginx
```

---

## 6. Backup

### 6.1 Database (SQLite = single file)
```bash
cp /var/www/sim-kk/apps/api/database/database.sqlite \
   /var/backups/simkk-$(date +%Y%m%d).sqlite
```

### 6.2 Cron harian
```bash
echo "0 2 * * * cp /var/www/sim-kk/apps/api/database/database.sqlite /var/backups/simkk-\$(date +\%Y\%m\%d).sqlite" \
  | sudo crontab -
```

### 6.3 Manual full backup
```bash
# Trigger via Manajer UI → "Trigger Backup" button di dashboard
# Or via API (Manajer only):
TOKEN=$(curl -s -X POST http://127.0.0.1/api/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"username":"manajer","password":"simkk-2026","level":"Manajer"}' | node -e "let d='';process.stdin.on('data',c=>d+=c).on('end',()=>console.log(JSON.parse(d).token))")
curl -s -X POST http://127.0.0.1/api/backup/trigger -H "Authorization: Bearer $TOKEN"
```

---

## 7. Troubleshooting

### Frontend blank / 502
```bash
sudo nginx -t           # cek config syntax
sudo systemctl restart nginx php8.3-fpm
ls -la /var/www/sim-kk/apps/web/dist/index.html   # harus exist
```

### API 404 di /api/login (GET should be 405, POST = 200)
```bash
cd /var/www/sim-kk/apps/api
php artisan route:clear
php artisan config:clear
php artisan route:cache
sudo systemctl reload nginx
```

### Login 401 "Username, password, atau level salah"
- Password default `simkk-2026` (lowercase, no caps)
- Cek: `php artisan tinker --execute="echo \App\Models\User::find(1)->password;"` — harusnya `$2y$...` hash
- Re-seed: `php artisan db:seed --class=UserSeeder --force`

### Login 200 tapi API return "readonly database" atau "Permission denied"
Permissions VPS issue:
```bash
sudo systemctl stop nginx php8.3-fpm
sudo chown -R www-data:www-data /var/www/sim-kk/apps/api/storage /var/www/sim-kk/apps/api/database /var/www/sim-kk/apps/api/bootstrap/cache
sudo chmod 664 /var/www/sim-kk/apps/api/database/database.sqlite
sudo systemctl start nginx php8.3-fpm
```

### Database locked (SQLite)
```bash
# Cari process yang hold file
lsof /var/www/sim-kk/apps/api/database/database.sqlite
# Kill stuck query
sudo kill -9 <pid>
```

### npm ci error "no such file or directory, open 'package-lock.json'"
```bash
cd /var/www/sim-kk/apps/web
ls -la package-lock.json   # harus exist
# Kalau gak ada, fallback:
npm install
```

### composer install error "requires ext-gd"
```bash
sudo apt install -y php8.3-gd
```

### Vite build error "JavaScript heap out of memory"
```bash
export NODE_OPTIONS="--max-old-space-size=2048"
npm run build
```

### "Permission denied" waktu git pull
```bash
sudo chown -R ubuntu:www-data /var/www/sim-kk
cd /var/www/sim-kk
git pull origin main
```

### Dropdown supplier kosong
- Cek API: `curl http://127.0.0.1/api/suppliers -H "Authorization: Bearer $TOKEN"`
- Kalau 0 results, re-seed: `php artisan db:seed --class=SupplierSeeder --force`
- Cek CORS: dari browser devtools network tab — kalo 404, biasanya nginx salah root

---

## 8. Files & Locations

| File | Path |
|---|---|
| API `.env` | `/var/www/sim-kk/apps/api/.env` |
| SQLite DB | `/var/www/sim-kk/apps/api/database/database.sqlite` |
| SPA build | `/var/www/sim-kk/apps/web/dist/` |
| Laravel logs | `/var/www/sim-kk/apps/api/storage/logs/laravel.log` |
| Nginx config | `/etc/nginx/sites-available/simkk` |
| Nginx error log | `/var/log/nginx/simkk-error.log` |
| PHP-FPM log | `/var/log/php8.3-fpm.log` |
| Backups | `/var/backups/simkk-YYYYMMDD.sqlite` |
| Repo | `/var/www/sim-kk` (branch `main`) |

---

## 9. State Management (current commit verified)

Latest commit verified live di VPS:
```
3c33e4e  feat(revisi): 11-item revisi implementation
3ab0969  docs: finalize E2E report with 11/11 revisi verified
244e40c  docs: E2E report
d368653  docs: E2E report
3fd2c36  docs: add comprehensive VPS deployment guide
```

11 revisi items verified by Playwright + Terminal E2E:
- ✅ R1: Booking system + dashboard widget
- ✅ R2: Time-slot anti-overlap
- ✅ R3: Supplier dropdown + Gudang grouping
- ✅ R4: Drag-down chevron visible
- ✅ R5: Live 30S hidden by default
- ✅ R6: Gudang grouped by kategori
- ✅ R7: Drawer pop-up (existing)
- ✅ R8: Rekam Medis session grouping
- ✅ R9: Multi-photo upload (max 10)
- ✅ R10: Role `<select>` dropdown, 5 options
- ✅ R11: Username/password by Manajer (Admin user seeded)

---

## 10. Out of scope (untuk catatan masa depan)

- HTTPS (Cloudflare Tunnel recommended — `deploy/cloudflare/05-tunnel.md`)
- Backup off-site (saat ini local `/var/backups/`)
- Monitoring (Prometheus + Grafana, atau SaaS seperti UptimeRobot)
- Log aggregation (Loki + Grafana)
- Multi-tenant (saat ini single-clinic)
- CI/CD (saat ini manual pull dari VPS)
