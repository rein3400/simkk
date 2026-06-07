#!/bin/bash
# Stage 5 v2 verify: nginx SPA + API
set -e
sudo -n bash -c "cp /tmp/sim-kk-v2.conf /etc/nginx/sites-available/sim-kk && nginx -t 2>&1"
sudo -n systemctl reload nginx
echo "=== TESTING ==="
echo "--- GET / (Vue SPA root) ---"
curl -s -o /tmp/spa.html -w "HTTP=%{http_code} type=%{content_type} size=%{size_download}\n" http://43.133.142.74/
echo "First 200 chars of /:"
head -c 200 /tmp/spa.html
echo ""
echo ""
echo "--- GET /api/health ---"
curl -s -o /dev/null -w "HTTP=%{http_code} type=%{content_type}\n" http://43.133.142.74/api/health
curl -s http://43.133.142.74/api/health
echo ""
echo ""
echo "--- POST /api/login (kasir) ---"
curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' | head -c 300
echo ""
echo ""
echo "--- GET /assets/* (Vue static) ---"
curl -s -o /dev/null -w "HTTP=%{http_code} type=%{content_type} size=%{size_download}\n" http://43.133.142.74/assets/index-BFXZ17Zt.js
