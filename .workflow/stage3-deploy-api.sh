#!/bin/bash
set -e
cd /var/www/sim-kk/apps/api
php artisan key:generate --force
php artisan migrate --force 2>&1 | tail -5
php artisan db:seed --class=ProductionBootstrapSeeder --force 2>&1 | tail -5
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link 2>&1 | tail -3
echo "=== USERS ==="
php artisan tinker --execute='echo \App\Models\User::count() . " users seeded";'
echo "=== ENV CHECK ==="
grep -E '^(APP_KEY|APP_URL|FRONTEND_URL|TELEGRAM_BOT_TOKEN)' .env
