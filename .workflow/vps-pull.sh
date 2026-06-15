#!/bin/bash
cd /var/www/sim-kk
git pull origin main
cd apps/api
composer dump-autoload
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan migrate --force
php artisan storage:link 2>&1 | head -3
php artisan config:cache
php artisan route:cache
sudo systemctl reload nginx
echo "=== PULLED ==="
