#!/bin/bash
set -e
# Use apps/api/public as root for PHP, SPA root for everything else
sudo tee /etc/nginx/sites-available/simkk > /dev/null <<'NGINX'
server {
    listen 80 default_server;
    server_name _;

    # PHP entry — Laravel public
    root /var/www/sim-kk/apps/api/public;
    index index.php index.html;

    # SPA assets & static
    location ^~ /assets/ {
        alias /var/www/sim-kk/apps/web/dist/assets/;
        expires 30d;
    }

    # SPA HTML fallback
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
NGINX
sudo nginx -t
sudo systemctl reload nginx
echo "=== NGINX FIXED ==="
