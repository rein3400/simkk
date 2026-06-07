#!/bin/bash
echo "=== Apply v17 (clean /api/ + /api/.*.php) ==="
sudo -n bash -c "cp /tmp/sim-kk-v17.conf /etc/nginx/sites-available/sim-kk && nginx -t 2>&1 && systemctl reload nginx"
sleep 1
echo "=== /api/login POST ==="
curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' -w "\nHTTP=%{http_code}\n" | head -c 500
echo ""
echo "=== /api/health ==="
curl -s -m 5 http://43.133.142.74/api/health -w "\nHTTP=%{http_code}\n"
echo "=== / SPA ==="
curl -s -o /dev/null -w "HTTP=%{http_code} size=%{size_download}\n" http://43.133.142.74/
echo "=== /assets/ ==="
curl -s -o /dev/null -w "HTTP=%{http_code} type=%{content_type}\n" http://43.133.142.74/assets/index-BxY11aAB.js
echo "=== error log ==="
sudo -n tail -3 /var/log/nginx/error.log
