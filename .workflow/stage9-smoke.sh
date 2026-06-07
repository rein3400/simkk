#!/bin/bash
# SIM-KK E2E smoke test
set -e
cd /var/www/sim-kk/apps/api
# payloads sudah di /tmp (owner ubuntu, readable by deploy via umask 022)

echo "=== LOGIN KASIR ==="
LOGIN=$(curl -s -X POST http://127.0.0.1/api/login -H "Content-Type: application/json" --data @/tmp/payload-login.json)
echo "$LOGIN" | head -c 400
echo ""
TOKEN=$(echo "$LOGIN" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["token"] ?? "";')
if [ -z "$TOKEN" ]; then echo "FAILED: no token"; exit 1; fi
echo "TOKEN OK (length=${#TOKEN})"

echo ""
echo "=== BOOTSTRAP ==="
curl -s http://127.0.0.1/api/bootstrap -H "Authorization: Bearer $TOKEN" | php -r '$d = json_decode(stream_get_contents(STDIN), true); foreach ($d as $k=>$v) { echo str_pad($k, 25) . (is_array($v) ? count($v) : (string)$v) . PHP_EOL; }'

echo ""
echo "=== POS PAY ==="
curl -s -X POST http://127.0.0.1/api/transactions/pay -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" --data @/tmp/payload-pay.json | head -c 600

echo ""
echo "=== DAILY REPORT EXPORT (Manajer) ==="
LOGIN_MJ=$(curl -s -X POST http://127.0.0.1/api/login -H "Content-Type: application/json" -d '{"username":"manajer","password":"simkk-2026","level":"Manajer"}')
TOKEN_MJ=$(echo "$LOGIN_MJ" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["token"] ?? "";')
curl -s -o /tmp/daily.pdf -w "HTTP=%{http_code} bytes=%{size_download}\n" http://127.0.0.1/api/daily-reports/2026-06-07/export -H "Authorization: Bearer $TOKEN_MJ"
file /tmp/daily.pdf

echo ""
echo "=== INVENTORY MOVEMENTS (Gudang) ==="
LOGIN_GD=$(curl -s -X POST http://127.0.0.1/api/login -H "Content-Type: application/json" -d '{"username":"gudang","password":"simkk-2026","level":"Gudang"}')
TOKEN_GD=$(echo "$LOGIN_GD" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["token"] ?? "";')
curl -s "http://127.0.0.1/api/inventory-movements?from=2026-06-01&to=2026-06-30" -H "Authorization: Bearer $TOKEN_GD" | head -c 500

echo ""
echo "=== TELEGRAM WEBHOOK GET (should return 200 healthcheck) ==="
curl -s -o /dev/null -w "HTTP=%{http_code}\n" http://127.0.0.1/api/telegram/webhook

echo ""
echo "=== FRONTEND (Vue SPA) ==="
curl -s -o /dev/null -w "HTTP=%{http_code} type=%{content_type}\n" http://127.0.0.1/
