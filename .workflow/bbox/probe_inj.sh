#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).kasir.token")
URL="http://127.0.0.1:8000/api/transactions/pay"

# 1. SQL injection in serviceId
echo "--- sqli_serviceid ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":"1 OR 1=1","qty":1}]}' "$URL"
echo ""

echo "--- sqli_drop ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":"1; DROP TABLE transaksi;--","qty":1}]}' "$URL"
echo ""

echo "--- xss_catatan ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}],"catatan":"<img src=x onerror=alert(1)>"}' "$URL"
echo ""

# 3. Bad UUID/token
echo "--- bad_token ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer abc.def.ghi" -H "Content-Type: application/json" \
  -d '{}' "$URL"
echo ""

echo "--- empty_token ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer " -H "Content-Type: application/json" \
  -d '{}' "$URL"
echo ""

echo "--- no_auth ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Content-Type: application/json" -d '{}' "$URL"
echo ""
