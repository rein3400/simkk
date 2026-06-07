#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).gudang.token")
URL="http://127.0.0.1:8000/api/inventory/purchases"

echo "--- flow3_purchase ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"BS-TEST-99","qty":50,"hpp":95000,"kadaluarsa":"2026-12-15","supplier":"Test Supplier"}' "$URL"
echo ""
