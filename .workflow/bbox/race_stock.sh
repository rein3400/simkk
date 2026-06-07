#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).kasir.token")
URL="http://127.0.0.1:8000/api/transactions/pay"
BODY='{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":8,"qty":10}],"catatan":"race-stock"}'

for i in 1 2 3 4 5; do
  curl -sS -o /tmp/rstock_$i.json -w "rstock$i STATUS=%{http_code} TIME=%{time_total}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d "$BODY" "$URL" &
done
wait
echo "--- responses ---"
for i in 1 2 3 4 5; do
  cat /tmp/rstock_$i.json
  echo ""
done
