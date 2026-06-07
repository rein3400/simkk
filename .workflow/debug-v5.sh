#!/bin/bash
echo "=== Recent error log (last 15) ==="
sudo -n tail -15 /var/log/nginx/error.log
echo ""
echo "=== Recent access log (last 10) ==="
sudo -n tail -10 /var/log/nginx/access.log
echo ""
echo "=== Reload + sleep + test ==="
sudo -n bash -c "systemctl reload nginx"
sleep 2
echo "=== Test login AGAIN ==="
curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' -w "\nHTTP=%{http_code}\n"
echo ""
echo "=== Test health ==="
curl -s http://43.133.142.74/api/health -w "\nHTTP=%{http_code}\n"
echo ""
echo "=== Last error after fresh test ==="
sudo -n tail -5 /var/log/nginx/error.log
