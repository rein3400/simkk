#!/bin/bash
TOKEN_T=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).terapis.token")
TOKEN_K=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).kasir.token")

# 1. Very long keluhan (1MB)
BIG=$(printf 'X%.0s' {1..1000000})
JSON=$(printf '{"terapis":"dr. Melati","judul":"X","catatan":"%s"}' "$BIG")
echo "--- 1mb_catatan ---"
echo "$JSON" | head -c 100
echo "$JSON" | curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN_T" -H "Content-Type: application/json" --data-binary @- "http://127.0.0.1:8000/api/patients/1/treatments" | head -c 200
echo ""

# 2. Very long catatan in pay
BIG=$(printf 'X%.0s' {1..1000000})
JSON=$(printf '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}],"catatan":"%s"}' "$BIG")
echo "--- 1mb_catatan_pay ---"
echo "$JSON" | curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN_K" -H "Content-Type: application/json" --data-binary @- "http://127.0.0.1:8000/api/transactions/pay" | head -c 300
echo ""

# 3. qty=0
echo "--- qty_0 ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN_K" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":0}]}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# 4. negative qty
echo "--- qty_neg ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN_K" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":-1}]}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# 5. duplicate items
echo "--- dup_items ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN_K" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1},{"serviceId":1,"qty":1}]}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# 6. items not array
echo "--- items_str ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN_K" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":"foo"}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# 7. unknown serviceId
echo "--- unknown_sid ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN_K" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":9999,"qty":1}]}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# 8. unknown pasien
echo "--- unknown_pasien ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN_K" -H "Content-Type: application/json" \
  -d '{"pasien_id":9999,"terapis_id":1,"items":[{"serviceId":1,"qty":1}]}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# 9. unknown terapis
echo "--- unknown_terapis ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN_K" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":9999,"items":[{"serviceId":1,"qty":1}]}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# 10. zero discount vs negative
echo "--- neg_discount ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN_K" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}],"discount":-100000}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# 11. discount > subtotal
echo "--- huge_discount ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN_K" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}],"discount":99999999}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""
