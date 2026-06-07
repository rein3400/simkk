#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token")
URL="http://127.0.0.1:8000/api/transactions/pay"

# Pay 1 paket (serviceId 9 = Paket Acne 3x @ 760000, commissionRate 0.11)
# Expected: komisi = round(760000 * 0.11) = 83600
echo "--- manajer pay paket ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":9,"qty":1}],"catatan":"flow4-paket"}' "$URL"
echo ""

echo "--- manajer pay paket bridal ---"
# 10 = Paket Bridal Glow 1450000 * 0.13 = 188500
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":10,"qty":1}],"catatan":"flow4-bridal"}' "$URL"
echo ""
