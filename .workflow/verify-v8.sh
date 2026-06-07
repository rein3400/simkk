#!/bin/bash
echo "=== Apply v8 ==="
sudo -n bash -c "cp /tmp/sim-kk-v8.conf /etc/nginx/sites-available/sim-kk && nginx -t 2>&1 && systemctl reload nginx"
sleep 1
echo "=== /api/health ==="
curl -s -m 5 -w "\nHTTP=%{http_code}\n" http://43.133.142.74/api/health
echo "=== login ==="
curl -s -m 5 -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' -w "\nHTTP=%{http_code}\n"
echo "=== / (SPA) ==="
curl -s -m 5 -o /dev/null -w "HTTP=%{http_code} size=%{size_download}\n" http://43.133.142.74/
echo "=== /assets/ (Vue) ==="
curl -s -m 5 -o /dev/null -w "HTTP=%{http_code} type=%{content_type}\n" http://43.133.142.74/assets/index-BFXZ17Zt.js
echo "=== error log ==="
sudo -n tail -3 /var/log/nginx/error.log
