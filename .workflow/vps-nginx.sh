#!/bin/bash
set -e

# 1. Nginx config
sudo tee /etc/nginx/sites-available/simkk > /dev/null <<'NGINX'
server {
    listen 80 default_server;
    server_name _;
    root /var/www/sim-kk/apps/web/dist;
    index index.html;

    # SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Laravel API
    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
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
NGINX

sudo ln -sf /etc/nginx/sites-available/simkk /etc/nginx/sites-enabled/simkk
sudo rm -f /etc/nginx/sites-enabled/default

# 2. Permissions
sudo chown -R ubuntu:www-data /var/www/sim-kk
sudo chmod -R 775 /var/www/sim-kk/apps/api/storage /var/www/sim-kk/apps/api/bootstrap/cache

# 3. Storage symlink
cd /var/www/sim-kk/apps/api
php artisan storage:link || true

# 4. Test & reload
sudo nginx -t
sudo systemctl reload nginx

echo "=== NGINX RELOADED ==="
