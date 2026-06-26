#!/bin/bash
# VPS auto-rebuild script — pulls latest, rebuilds SPA, clears Laravel route/cache,
# reloads nginx. Usage: bash .workflow/vps-rebuild.sh
set -e
cd /var/www/sim-kk
git pull origin main

# Backend: clear caches + run any new migrations + re-seed if needed.
cd apps/api
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan migrate --force 2>&1 | tail -3 || true

# Frontend: rebuild SPA.
cd ../web
npm ci --prefer-offline --no-audit --progress=false
npm run build

# Reload web server.
cd ..
sudo systemctl reload nginx

echo "=== REBUILD DONE ==="
git log --oneline -1
ls -la apps/web/dist/assets/ | grep -E 'index-[A-Za-z0-9_-]+\.(js|css)$'
