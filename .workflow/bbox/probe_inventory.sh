#!/bin/bash
# Try POST /api/inventory and PUT /api/inventory/{id}
TK=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).gudang.token")
TK_M=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token")

for p in /api/inventory /api/inventory/1 /api/inventory/1/batches /api/inventory/products /api/produk /api/batches; do
  for tname in gudang manajer kasir; do
    T=$(eval echo \$TK_$(echo $tname | head -c 1 | tr a-z A-Z))
  done
done

# Direct
for p in /api/inventory /api/inventory/1 /api/inventory/1/batches /api/inventory/products /api/produk; do
  for tname in gudang manajer kasir terapis; do
    case $tname in
      gudang) T=$TK ;;
      manajer) T=$TK_M ;;
      kasir) T=$(cat /tmp/kasir_tk) ;;
      *) T=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).terapis.token") ;;
    esac
    STATUS=$(curl -sS -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $T" "http://127.0.0.1:8000$p")
    echo "GET $p ($tname): $STATUS"
  done
done

# Also try to create batch on different endpoints
echo "--- POST /api/inventory ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"X","qty":1,"hpp":1,"kadaluarsa":"2026-12-15","supplier":"X"}' "http://127.0.0.1:8000/api/inventory"
echo ""

# Negative qty batch
echo "--- neg_qty_purchases ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"X","qty":-1,"hpp":1,"kadaluarsa":"2026-12-15","supplier":"X"}' "http://127.0.0.1:8000/api/inventory/purchases"
echo ""

# Duplicate kode_batch
echo "--- dup_kode ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"BS-0526-B","qty":5,"hpp":90000,"kadaluarsa":"2026-12-15","supplier":"X"}' "http://127.0.0.1:8000/api/inventory/purchases"
echo ""

# kode_batch with weird chars
echo "--- weird_kode ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"../../etc/passwd","qty":1,"hpp":1,"kadaluarsa":"2026-12-15","supplier":"X"}' "http://127.0.0.1:8000/api/inventory/purchases"
echo ""

# XSS in supplier
echo "--- xss_supplier ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"XSS-1","qty":1,"hpp":1,"kadaluarsa":"2026-12-15","supplier":"<script>alert(1)</script>"}' "http://127.0.0.1:8000/api/inventory/purchases"
echo ""
