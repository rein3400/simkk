#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).kasir.token")
URL="http://127.0.0.1:8000/api/transactions/pay"
# Calming Toner has 7 left, ask for 5 x 5 = 25 via 5 parallel
BODY='{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":8,"qty":5}],"catatan":"race-stock2"}'

for i in 1 2 3 4 5; do
  curl -sS -o /tmp/rs2_$i.json -w "rs2$i STATUS=%{http_code} TIME=%{time_total}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d "$BODY" "$URL" &
done
wait
echo "--- responses ---"
for i in 1 2 3 4 5; do
  cat /tmp/rs2_$i.json | head -c 200
  echo ""
done
