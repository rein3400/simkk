#!/bin/bash
echo "=== Apply v6 (separate port 8080 for API) ==="
sudo -n bash -c "cp /tmp/sim-kk-v6.conf /etc/nginx/sites-available/sim-kk && nginx -t 2>&1 && systemctl reload nginx"
sleep 1
echo ""
echo "=== Test SPA on port 80 ==="
curl -s -o /dev/null -w "GET / = HTTP=%{http_code} size=%{size_download}\n" http://43.133.142.74/
echo ""
echo "=== Test API on port 8080 ==="
curl -s -o /tmp/health.txt -w "GET /api/health = HTTP=%{http_code}\n" http://43.133.142.74:8080/api/health
cat /tmp/health.txt
echo ""
echo ""
echo "=== Test login (POST) on 8080 ==="
curl -s -X POST http://43.133.142.74:8080/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' | head -c 300
echo ""
echo ""
echo "=== Recent error log (last 5) ==="
sudo -n tail -5 /var/log/nginx/error.log
