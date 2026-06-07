#!/bin/bash
echo "=== Apply v15 (FINAL: same-origin with rewrite strip /api) ==="
sudo -n bash -c "cp /tmp/sim-kk-v15.conf /etc/nginx/sites-available/sim-kk && nginx -t 2>&1 && systemctl reload nginx"
sleep 1
echo ""
echo "=== Test SPA ==="
curl -s -o /dev/null -w "GET / HTTP=%{http_code} size=%{size_download}\n" http://43.133.142.74/
echo ""
echo "=== Test /api/login from IP (no Host header) ==="
curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' -w "\nHTTP=%{http_code}\n" | head -c 400
echo ""
echo "=== Test /api/health ==="
curl -s -m 5 http://43.133.142.74/api/health -w "\nHTTP=%{http_code}\n"
echo ""
echo "=== Test /assets/ ==="
curl -s -o /dev/null -w "HTTP=%{http_code} type=%{content_type}\n" http://43.133.142.74/assets/index-BxY11aAB.js
echo ""
echo "=== error log ==="
sudo -n tail -5 /var/log/nginx/error.log
