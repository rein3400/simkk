#!/bin/bash
echo "=== Apply v14 (subdomain API) ==="
sudo -n bash -c "cp /tmp/sim-kk-v14.conf /etc/nginx/sites-available/sim-kk && nginx -t 2>&1 && systemctl reload nginx"
sleep 1
echo ""
echo "=== Test SPA on port 80 (default IP) ==="
curl -s -o /dev/null -w "GET / HTTP=%{http_code} size=%{size_download}\n" http://43.133.142.74/
echo ""
echo "=== Test API on subdomain (Host: api.sim-kk.example.id) ==="
curl -s -m 5 -H "Host: api.sim-kk.example.id" http://43.133.142.74/api/health
echo ""
echo "=== Test login via subdomain ==="
curl -s -X POST -H "Host: api.sim-kk.example.id" -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' http://43.133.142.74/api/login -w "\nHTTP=%{http_code}\n" | head -c 500
echo ""
echo "=== Test assets ==="
curl -s -o /dev/null -w "GET /assets/* HTTP=%{http_code} type=%{content_type}\n" http://43.133.142.74/assets/index-BFXZ17Zt.js
