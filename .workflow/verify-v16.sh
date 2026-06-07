#!/bin/bash
echo "=== Apply v16 (alias + nested /index.php) ==="
sudo -n bash -c "cp /tmp/sim-kk-v16.conf /etc/nginx/sites-available/sim-kk && nginx -t 2>&1 && systemctl reload nginx"
sleep 1
echo ""
echo "=== Test /api/login (POST from IP) ==="
curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' -w "\nHTTP=%{http_code}\n" | head -c 400
echo ""
echo "=== Test /api/health ==="
curl -s -m 5 http://43.133.142.74/api/health -w "\nHTTP=%{http_code}\n"
echo ""
echo "=== Test SPA ==="
curl -s -o /dev/null -w "GET / HTTP=%{http_code} size=%{size_download}\n" http://43.133.142.74/
echo ""
echo "=== Test /assets/ ==="
curl -s -o /dev/null -w "HTTP=%{http_code} type=%{content_type}\n" http://43.133.142.74/assets/index-BxY11aAB.js
