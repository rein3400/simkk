#!/bin/bash
TK=$(cat /tmp/gudang_tk)
URL="http://127.0.0.1:8000/api/inventory/purchases"

echo "--- neg_qty ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"X","qty":-1,"hpp":1,"kadaluarsa":"2026-12-15","supplier":"X"}' "$URL"
echo ""

echo "--- dup_kode ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"BS-0526-B","qty":5,"hpp":90000,"kadaluarsa":"2026-12-15","supplier":"X"}' "$URL"
echo ""

echo "--- weird_kode ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"../../etc/passwd","qty":1,"hpp":1,"kadaluarsa":"2026-12-15","supplier":"X"}' "$URL"
echo ""

echo "--- xss_supplier ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"XSS-1","qty":1,"hpp":1,"kadaluarsa":"2026-12-15","supplier":"<script>alert(1)</script>"}' "$URL"
echo ""

echo "--- hpp_negative ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"NEGHPP-1","qty":1,"hpp":-1,"kadaluarsa":"2026-12-15","supplier":"X"}' "$URL"
echo ""

echo "--- missing_supplier ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"kode_batch":"NOSUP-1","qty":1,"hpp":1,"kadaluarsa":"2026-12-15"}' "$URL"
echo ""

echo "--- missing_kode ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" \
  -d '{"produk_id":1,"qty":1,"hpp":1,"kadaluarsa":"2026-12-15","supplier":"X"}' "$URL"
echo ""
