#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).gudang.token")
URL="http://127.0.0.1:8000/api/inventory/purchases"

# Try common shapes
echo "--- snake_basic ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"qty":50,"hpp":95000,"kadaluarsa":"2026-12-15","supplier":"Test Supplier"}' "$URL"
echo ""

echo "--- camel ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"produkId":1,"qty":50,"hpp":95000,"kadaluarsa":"2026-12-15","supplier":"Test Supplier"}' "$URL"
echo ""

echo "--- expiry_camel ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"qty":50,"hpp":95000,"expiry":"2026-12-15","supplier":"Test Supplier"}' "$URL"
echo ""

echo "--- empty ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{}' "$URL"
echo ""

echo "--- bad_produk ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"produk_id":99999,"qty":50,"hpp":95000,"kadaluarsa":"2026-12-15","supplier":"X"}' "$URL"
echo ""

echo "--- negative_qty ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"qty":-10,"hpp":95000,"kadaluarsa":"2026-12-15","supplier":"X"}' "$URL"
echo ""

echo "--- zero_qty ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"qty":0,"hpp":95000,"kadaluarsa":"2026-12-15","supplier":"X"}' "$URL"
echo ""

echo "--- bad_date ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"qty":50,"hpp":95000,"kadaluarsa":"yesterday","supplier":"X"}' "$URL"
echo ""

echo "--- past_date ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"qty":50,"hpp":95000,"kadaluarsa":"2020-01-01","supplier":"X"}' "$URL"
echo ""

echo "--- float_qty ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"qty":1.5,"hpp":95000,"kadaluarsa":"2026-12-15","supplier":"X"}' "$URL"
echo ""

echo "--- string_hpp ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"qty":50,"hpp":"Rp95.000","kadaluarsa":"2026-12-15","supplier":"X"}' "$URL"
echo ""
