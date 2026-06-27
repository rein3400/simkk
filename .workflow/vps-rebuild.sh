#!/bin/bash
# VPS auto-rebuild script — pulls latest, rebuilds SPA, clears Laravel route/cache,
# reloads nginx. Usage: bash .workflow/vps-rebuild.sh
set -e
cd /var/www/sim-kk

# Stop web server + PHP-FPM BEFORE git pull — they hold file handles that
# prevent overwriting controller files.
sudo systemctl stop nginx php8.3-fpm || true

# chown whole tree to ubuntu so git pull can overwrite.
sudo chown -R ubuntu:ubuntu /var/www/sim-kk

git pull origin main

# Re-apply www-data ownership for runtime (storage/logs/db).
sudo chown -R www-data:www-data /var/www/sim-kk/apps/api/storage /var/www/sim-kk/apps/api/bootstrap/cache /var/www/sim-kk/apps/api/database

# Backend: clear caches + run any new migrations.
cd apps/api
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan migrate --force 2>&1 | tail -3 || true

# Frontend: rebuild SPA.
cd ../web
npm ci --prefer-offline --no-audit --progress=false
npm run build

# Restart web server.
cd ..
sudo systemctl start php8.3-fpm nginx
sudo systemctl reload nginx

echo "=== REBUILD DONE ==="
git log --oneline -1
ls -la apps/web/dist/assets/ | grep -E 'index-[A-Za-z0-9_-]+\.(js|css)$'
