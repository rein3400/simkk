# SIM-KK — VPS Deployment Guide

Deploy SIM-KK ke Ubuntu 24.04 VPS (Tencent CVM S5.SMALL2 atau setara).
Tested on Tencent CVM S5.SMALL2, 2 vCPU / 2 GB RAM, Ubuntu 24.04 LTS.

> **One-line summary:** Clone repo → install PHP/Node/nginx → composer install
> → migrate → seed → build SPA → nginx config. Total: ~15 menit.

---

## 1. Persiapan Server (sekali seumur hidup)

Login ke VPS pakai SSH key atau password:
```bash
ssh ubuntu@43.133.142.74
```

### 1.1 Update + firewall
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

### 1.3 Clone repo
```bash
sudo mkdir -p /var/www
sudo chown ubuntu:ubuntu /var/www
cd /var/www
git clone https://github.com/rein3400/simkk.git sim-kk
cd sim-kk
git checkout main
```

---

## 2. Konfigurasi API

### 2.1 Buat `.env` (production)
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

# Optional — kalau pakai Cloudflare R2 untuk clinical photos
R2_ENDPOINT=
R2_BUCKET=simkk-clinical
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_PUBLIC_URL=
EOF
```

### 2.2 Install deps + migrate + seed
```bash
cd /var/www/sim-kk/apps/api
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --class=UserSeeder --force
php artisan storage:link
```

> **Catatan:** kalau `package:discover` gagal dengan error `dotenv whitespace`,
> itu karena `APP_KEY=base64:$(...)` di-paste mentah. Generate key secara
> terpisah seperti di atas, JANGAN pakai `$(...)` di dalam heredoc.

---

## 3. Konfigurasi Frontend

```bash
cd /var/www/sim-kk/apps/web
npm ci
npm run build
```

Build output di `dist/`. Static file-nya di-serve nginx.

---

## 4. Nginx Config (SPA + API on same origin)

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

---

## 5. Permissions (WAJIB)

Tanpa step ini, SQLite + storage log akan `Permission denied`:

```bash
sudo chown -R www-data:www-data /var/www/sim-kk/apps/api/storage
sudo chown -R www-data:www-data /var/www/sim-kk/apps/api/bootstrap/cache
sudo chown -R www-data:www-data /var/www/sim-kk/apps/api/database
sudo chmod -R 775 /var/www/sim-kk/apps/api/storage
sudo chmod -R 775 /var/www/sim-kk/apps/api/bootstrap/cache
sudo chmod 664 /var/www/sim-kk/apps/api/database/database.sqlite
sudo chown -R ubuntu:www-data /var/www/sim-kk
```

---

## 6. Enable services

```bash
sudo systemctl enable nginx php8.3-fpm
sudo systemctl restart nginx php8.3-fpm
```

---

## 7. Verifikasi

```bash
# Frontend (SPA HTML)
curl -sI http://127.0.0.1/
# Expected: 200 OK, Content-Type: text/html

# API routing (kalau GET, harus 405 Method Not Allowed, bukan 404)
curl -sI http://127.0.0.1/api/login
# Expected: HTTP/1.1 405 Method Not Allowed

# Login beneran
curl -s -X POST http://127.0.0.1/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"username":"manajer","password":"simkk-2026","role":"Manajer"}'
# Expected: {"token":"...","user":{...}}
```

Buka **http://43.133.142.74/** di browser lokal → login pakai:

| Username | Password   | Role     |
|----------|------------|----------|
| manajer  | simkk-2026 | Manajer  |
| kasir    | simkk-2026 | Kasir    |
| terapis  | simkk-2026 | Terapis  |
| gudang   | simkk-2026 | Gudang   |

---

## 8. Update Workflow (perubahan code)

Setiap kali ada perubahan di local dan sudah di-push ke GitHub:

```bash
cd /var/www/sim-kk
git pull origin main
cd apps/api
composer dump-autoload
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan migrate --force
php artisan storage:link 2>&1 | tail -1
php artisan config:cache
php artisan route:cache
cd ../web
npm ci
npm run build
sudo systemctl reload nginx
```

Atau satu baris (copy-paste):
```bash
cd /var/www/sim-kk && git pull origin main && cd apps/api && composer dump-autoload && php artisan config:clear && php artisan route:clear && php artisan cache:clear && php artisan migrate --force && php artisan storage:link 2>&1 | tail -1 && php artisan config:cache && php artisan route:cache && cd ../web && npm ci && npm run build && sudo systemctl reload nginx && echo "=== UPDATED ==="
```

---

## 9. HTTPS (Optional — pakai Cloudflare Tunnel)

Kalau pakai Cloudflare Tunnel (recommended — no SSL cert renewal):
```bash
# Install cloudflared
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg \
  | sudo tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
echo 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared noble main' \
  | sudo tee /etc/apt/sources.list.d/cloudflared.list
sudo apt update && sudo apt install -y cloudflared

# Login + create tunnel
cloudflared tunnel login
cloudflared tunnel create sim-kk
cloudflared tunnel route dns sim-kk api.yourdomain.com

# Run tunnel pointing ke localhost:80
cloudflared tunnel run sim-kk
```

Detail ada di `deploy/cloudflare/05-tunnel.md`.

---

## 10. Troubleshooting

### API 404 padahal `/api/login` udah di-request
Nginx config root salah. Cek `root /var/www/sim-kk/apps/api/public;` di
`/etc/nginx/sites-available/simkk` lalu `sudo nginx -t && sudo systemctl reload nginx`.

### Login 401 "Username, password, atau level salah"
Pastikan kirim field `role` (atau `level`). Frontend harus sertakan salah satu.
Backend AuthController pakai `required_without`.

### Login 200 tapi `attempt to write a readonly database`
Permissions DB belum di-set. Jalankan step 5 (permissions).

### SPA blank / `404 Not Found` di route tertentu
Cek `try_files $uri $uri/ /index.html;` ada di `location /` nginx config.
Jangan lupa SPA `root` di-override per location.

### `npm ci` error "no such file or directory, open 'package-lock.json'"
Pastikan `apps/web/package-lock.json` ada di repo (biasanya ke-gitignore
sengaja). Kalau gak ada, ganti `npm ci` dengan `npm install`.

### `composer install` error "requires ext-gd"
```bash
sudo apt install -y php8.3-gd
```

### `package:discover` / `key:generate` error "Invalid route action"
Biasanya `vendor/autoload.php` stale atau class hilang di git push.
VPS fix:
```bash
cd /var/www/sim-kk/apps/api
composer dump-autoload
php artisan config:clear
php artisan route:clear
php artisan route:list --path=api  # harus list routes
```

---

## 11. Backup

Database SQLite cukup copy file:
```bash
cp /var/www/sim-kk/apps/api/database/database.sqlite \
   /var/backups/simkk-$(date +%Y%m%d).sqlite
```

Cron harian:
```bash
echo "0 2 * * * cp /var/www/sim-kk/apps/api/database/database.sqlite /var/backups/simkk-\$(date +\%Y\%m\%d).sqlite" \
  | sudo crontab -
```

---

## 12. Test Stack

| Test           | URL                                 | Expected          |
|----------------|-------------------------------------|-------------------|
| SPA root       | `http://43.133.142.74/`             | 200 + HTML        |
| SPA fallback   | `http://43.133.142.74/pos`          | 200 + HTML        |
| API GET 405    | `http://43.133.142.74/api/login`    | 405 (POST only)   |
| API POST 200   | `curl -X POST /api/login ...`       | `{"token":...}`   |
| Storage asset  | `http://43.133.142.74/storage/...`  | file or 404       |

E2E full suite (74 tests, 4 roles, 12 sections): jalanin `.workflow/e2e-deep.js`
dari local setelah update.

---

## 13. Defaults Recap

| Component       | Value                                  |
|-----------------|----------------------------------------|
| Repo            | https://github.com/rein3400/simkk      |
| Branch          | `main` (was `master`)                  |
| App dir         | `/var/www/sim-kk`                      |
| API public dir  | `/var/www/sim-kk/apps/api/public`      |
| SPA dist dir    | `/var/www/sim-kk/apps/web/dist`        |
| DB file         | `/var/www/sim-kk/apps/api/database/database.sqlite` |
| Nginx site      | `/etc/nginx/sites-available/simkk`     |
| Default users   | `manajer`/`kasir`/`terapis`/`gudang` (all `simkk-2026`) |
| Test logins     | `manajer` / `simkk-2026` for full access |
| Token TTL       | 7 days (10080 min, SANCTUM_TOKEN_EXPIRATION_MINUTES) |
| Test endpoint   | `curl -s http://43.133.142.74/api/login -X POST ...` |
