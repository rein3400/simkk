#!/bin/bash
TK=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).kasir.token")

# Try discount as a string
echo "--- discount_str ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}],"discount":"50000"}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# Float discount
echo "--- discount_float ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}],"discount":50000.5}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# discount equals subtotal
echo "--- discount_equal ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}],"discount":285000}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# discount = subtotal + 1
echo "--- discount_over ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}],"discount":285001}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# Big qty
echo "--- qty_big ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":99999}]}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# Empty items array
echo "--- items_empty ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"pasien_id":1,"terapis_id":1,"items":[]}' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# Invalid Content-Type
echo "--- xml_ct ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/xml" \
  -d '<root/>' "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# No body
echo "--- no_body ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" "http://127.0.0.1:8000/api/transactions/pay"
echo ""

# Form-encoded body
echo "--- form_body ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" --data-urlencode "pasien_id=1" --data-urlencode "terapis_id=1" --data-urlencode "items[0][serviceId]=1" "http://127.0.0.1:8000/api/transactions/pay"
echo ""
