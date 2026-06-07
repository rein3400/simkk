#!/bin/bash
set -e
echo "=== Apply v4 (alias approach) ==="
sudo -n bash -c "cp /tmp/sim-kk-v4.conf /etc/nginx/sites-available/sim-kk && nginx -t 2>&1 && systemctl reload nginx"
sleep 1
echo ""
echo "=== Test API after v4 ==="
curl -s -o /tmp/health.txt -w "GET /api/health = HTTP=%{http_code}\n" http://43.133.142.74/api/health
cat /tmp/health.txt
echo ""
echo ""
echo "=== Test login (POST) ==="
curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' | head -c 300
echo ""
echo ""
echo "=== Test SPA root ==="
curl -s -o /dev/null -w "GET / = HTTP=%{http_code} size=%{size_download}\n" http://43.133.142.74/
echo ""
echo "=== Recent error log (last 3) ==="
sudo -n tail -3 /var/log/nginx/error.log
