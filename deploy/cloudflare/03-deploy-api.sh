#!/usr/bin/env bash
# 03 — Deploy Laravel API ke VPS
# Run di LOCAL terminal (bukan di VPS) — script ini SSH ke VPS
# Usage: bash 03-deploy-api.sh <VPS_IP> <VPS_USER>

set -euo pipefail

VPS_IP="${1:?Usage: $0 <VPS_IP> <VPS_USER>}"
VPS_USER="${2:?Usage: $0 <VPS_IP> <VPS_USER>}"

# Konfirmasi
echo "Deploying to ${VPS_USER}@${VPS_IP}"
read -p "Continue? (yes/no): " confirm
[[ "$confirm" == "yes" ]] || { echo "Aborted."; exit 1; }

SSH="ssh -o StrictHostKeyChecking=accept-new ${VPS_USER}@${VPS_IP}"

echo "=== 1. SSH test ==="
$SSH "echo OK; whoami; pwd"

echo "=== 2. Install cloudflared (untuk tunnel nanti) ==="
$SSH "curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | sudo tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
echo 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared focal main' | sudo tee /etc/apt/sources.list.d/cloudflared.list
sudo apt update && sudo apt install -y cloudflared"

echo "=== 3. Clone repo ==="
$SSH "cd ~ && [[ -d sim-kk ]] || git clone https://github.com/<YOUR_GH_USER>/sim-kk.git"
# Ganti <YOUR_GH_USER> dengan username GitHub lo

echo "=== 4. Composer install ==="
$SSH "cd ~/sim-kk/apps/api && composer install --no-dev --optimize-autoloader --no-interaction"

echo "=== 5. Copy .env.example → .env, generate APP_KEY ==="
$SSH "cd ~/sim-kk/apps/api && [[ -f .env ]] || cp .env.example .env"
$SSH "cd ~/sim-kk/apps/api && php artisan key:generate --force"

echo "=== 6. Storage link + migrate + seed ==="
$SSH "cd ~/sim-kk/apps/api && php artisan storage:link 2>&1 | tail -1 || true"
$SSH "cd ~/sim-kk/apps/api && php artisan migrate --force"
$SSH "cd ~/sim-kk/apps/api && php artisan db:seed --force"

echo "=== 7. Configure nginx vhost ==="
$SSH "sudo tee /etc/nginx/sites-available/simkk <<'EOF'
server {
    listen 80 default_server;
    server_name _;
    root /home/${VPS_USER}/sim-kk/apps/api/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options \"SAMEORIGIN\" always;
    add_header X-Content-Type-Options \"nosniff\" always;
    add_header X-XSS-Protection \"1; mode=block\" always;
    add_header Referrer-Policy \"no-referrer-when-downgrade\" always;
    add_header Strict-Transport-Security \"max-age=31536000; includeSubDomains\" always;
    client_max_body_size 20M;

    # API routes
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 60;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
sudo ln -sf /etc/nginx/sites-available/simkk /etc/nginx/sites-enabled/simkk
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx"

echo "=== 8. Local health check ==="
$SSH "curl -sS http://127.0.0.1/api/health"

echo ""
echo "=== DONE ==="
echo "API accessible di http://${VPS_IP}/api/health (HTTP only, akan di-Tunnel oleh step 05)"
echo "Lanjut ke step 04-pages.md untuk setup Cloudflare Pages (FE)"
