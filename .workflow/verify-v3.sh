#!/bin/bash
echo "=== Test API after v3 fix ==="
curl -s -o /tmp/health.txt -w "GET /api/health = HTTP=%{http_code}\n" http://43.133.142.74/api/health
cat /tmp/health.txt
echo ""
echo ""
echo "=== Test login (POST) ==="
curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' | head -c 300
echo ""
echo ""
echo "=== Test SPA ==="
curl -s -o /dev/null -w "GET / = HTTP=%{http_code} size=%{size_download}\n" http://43.133.142.74/
echo ""
echo "=== Recent error log (last 3) ==="
sudo -n tail -3 /var/log/nginx/error.log
