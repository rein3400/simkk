#!/bin/bash
# Paste this script into SumoPod Web Console for one-click deploy.
# It pulls the latest master, builds the SPA locally on VPS, copies files,
# runs migrations, clears caches, and reloads nginx.

set -e
cd /var/www/sim-kk

echo "=== SIM-KK Web Console Deploy ==="
echo "Pulling latest code..."
git pull origin master

echo "Installing PHP dependencies..."
cd apps/api
composer install --no-dev --optimize-autoloader 2>&1 | tail -3

echo "Running migrations..."
php artisan migrate --force 2>&1 | tail -5

echo "Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

echo "Building Vue SPA..."
cd ../web
npm ci 2>&1 | tail -3
npm run build 2>&1 | tail -5

echo "Reloading nginx..."
sudo systemctl reload nginx

echo ""
echo "=== POST-DEPLOY SMOKE ==="
curl -s -o /dev/null -w "SPA: HTTP=%{http_code} size=%{size_download}\n" http://127.0.0.1/
curl -s -o /dev/null -w "API Health: HTTP=%{http_code}\n" http://127.0.0.1/api/health
LOGIN=$(curl -s -X POST http://127.0.0.1/api/login -H "Content-Type: application/json" -d '{"username":"manajer","password":"simkk-2026","level":"Manajer"}')
TOKEN=$(echo "$LOGIN" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["token"] ?? "";')
echo "Manajer token: ${#TOKEN} chars"
curl -s -H "Authorization: Bearer $TOKEN" http://127.0.0.1/api/bootstrap | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo "Bootstrap: patients=".count($d["patients"]??[])." services=".count($d["services"]??[])." inventory=".count($d["inventory"]??[])."\n";'

echo ""
echo "=== DEPLOY COMPLETE ==="
