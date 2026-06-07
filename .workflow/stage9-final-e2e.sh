#!/bin/bash
# FINAL DEEP E2E — 6 categories, 25+ tests
# Uses Host: api.sim-kk.example.id header so requests hit the API server block
set +e
BASE="http://43.133.142.74"
HOST="api.sim-kk.example.id"
PASS=0
FAIL=0
FAIL_LIST=""

ok() { echo "  ✓ $1"; PASS=$((PASS+1)); }
ko() { echo "  ✗ $1"; echo "    reason: $2" | head -c 250; echo ""; FAIL=$((FAIL+1)); FAIL_LIST="$FAIL_LIST\n  - $1"; }

curl_api() {
  curl -s -H "Host: $HOST" "$@"
}
curl_api_post() {
  curl -s -X POST -H "Host: $HOST" -H "Content-Type: application/json" "$@"
}

echo "=========================================="
echo "DEEP E2E SUITE — final, post-fix"
echo "=========================================="

# --- 1. LOGIN all roles ---
echo ""
echo "[1/6] LOGIN all 4 roles"
declare -A CAP=( [kasir]=Kasir [terapis]=Terapis [gudang]=Gudang [manajer]=Manajer )
declare -A TKN
for role in kasir terapis gudang manajer; do
  cap=${CAP[$role]}
  R=$(curl_api_post -d "{\"username\":\"$role\",\"password\":\"simkk-2026\",\"level\":\"$cap\"}" $BASE/api/login)
  T=$(echo "$R" | grep -o '"token":"[^"]*"' | head -1 | cut -d'"' -f4)
  if [ -n "$T" ] && [ ${#T} -gt 10 ]; then
    TKN[$role]=$T
    ok "$role → token ${#T} chars"
  else
    ko "$role login" "$R"
  fi
done

# Wrong password
R=$(curl_api_post -d '{"username":"kasir","password":"WRONG","level":"Kasir"}' $BASE/api/login)
if echo "$R" | grep -q "401\|message"; then
  ok "wrong password rejected"
else
  ko "wrong password" "$R"
fi

# --- 2. AUTH + RBAC ---
echo ""
echo "[2/6] AUTH + RBAC enforcement"
KTOK=${TKN[kasir]}; MTOK=${TKN[manajer]}; TTOK=${TKN[terapis]}; GTOK=${TKN[gudang]}

# Kasir POST Gudang endpoint
R=$(curl_api -H "Authorization: Bearer $KTOK" -X POST $BASE/api/inventory/purchases -H "Content-Type: application/json" -d '{"produk_id":1,"kode_batch":"X","qty":1,"hpp":1,"supplier":"X","kadaluarsa":"2027-01-01"}')
if echo "$R" | grep -q "403\|role"; then ok "Kasir blocked from Gudang"; else ko "Kasir→Gudang" "$R"; fi

# No token
R=$(curl_api $BASE/api/bootstrap)
if echo "$R" | grep -q "401\|Unauthorized\|token"; then ok "No token rejected"; else ko "no token" "$R"; fi

# Invalid token
R=$(curl_api -H "Authorization: Bearer INVALID" $BASE/api/bootstrap)
if echo "$R" | grep -q "401\|Unauthorized"; then ok "Invalid token rejected"; else ko "bad token" "$R"; fi

# Kasir hit Manajer-only report
R=$(curl_api -H "Authorization: Bearer $KTOK" $BASE/api/daily-reports/2026-06-07/export)
if echo "$R" | grep -q "403\|role"; then ok "Kasir blocked from Manajer report"; else ko "Kasir→Manajer" "$R"; fi

# --- 3. POS TRANSAKSI ---
echo ""
echo "[3/6] POS TRANSAKSI flow"
# Bootstrap has inventory
R=$(curl_api -H "Authorization: Bearer $MTOK" $BASE/api/bootstrap)
INV=$(echo "$R" | grep -o '"inventory":\[' | head -1)
if [ -n "$INV" ]; then ok "Bootstrap returns inventory"; else ko "no inventory in bootstrap" "$R" | head -c 200; fi

# POS pay with valid service
R=$(curl_api -H "Authorization: Bearer $KTOK" -X POST $BASE/api/transactions/pay -H "Content-Type: application/json" -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}],"discount":0,"metode_bayar":"Tunai"}')
if echo "$R" | grep -q '"id":"TRX'; then
  ok "POS pay: $(echo "$R" | grep -o 'TRX-[0-9]*-[0-9]*' | head -1)"
else
  ko "POS pay" "$R" | head -c 200
fi

# Pay with insufficient stock
R=$(curl_api -H "Authorization: Bearer $KTOK" -X POST $BASE/api/transactions/pay -H "Content-Type: application/json" -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":99999}],"discount":0,"metode_bayar":"Tunai"}')
if echo "$R" | grep -q "422\|tidak cukup\|insufficient"; then ok "Insufficient stock → 422"; else ko "insufficient stock" "$R" | head -c 200; fi

# Bad serviceId
R=$(curl_api -H "Authorization: Bearer $KTOK" -X POST $BASE/api/transactions/pay -H "Content-Type: application/json" -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":99999,"qty":1}],"discount":0,"metode_bayar":"Tunai"}')
if echo "$R" | grep -q "422\|not found"; then ok "Bad serviceId rejected"; else ko "bad serviceId" "$R" | head -c 200; fi

# --- 4. REKAM MEDIS + FOTO ---
echo ""
echo "[4/6] REKAM MEDIS + FOTO"
# Add treatment
R=$(curl_api -H "Authorization: Bearer $TTOK" -X POST $BASE/api/patients/1/treatments -H "Content-Type: application/json" -d '{"judul":"Test treatment","catatan":"Catatan test"}')
if echo "$R" | grep -q '"id"'; then ok "Add treatment OK"; else ko "add treatment" "$R" | head -c 200; fi

# Upload foto
PNG='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAAAAADZOFXAAAADklEQVR4nGP4DwUMlDEA98A/wbI0QbsAAAAASUVORK5CYII='
JBODY='{"label":"After","filename":"test-final.png","content":"'$PNG'","mimeType":"image/png"}'
R=$(curl_api -H "Authorization: Bearer $TTOK" -X POST $BASE/api/patients/1/photos -H "Content-Type: application/json" -d "$JBODY")
if echo "$R" | grep -q '"objectRef"\|"id"'; then ok "Upload foto to R2 OK"; else ko "upload foto" "$R" | head -c 200; fi

# Terapis wrong pasien
R=$(curl_api -H "Authorization: Bearer $TTOK" -X POST $BASE/api/patients/2/photos -H "Content-Type: application/json" -d "$JBODY")
if echo "$R" | grep -q "403\|bukan"; then ok "Terapis ownership check"; else ko "ownership" "$R" | head -c 200; fi

# Path traversal
JBODY_BAD='{"label":"X","filename":"../../../etc/passwd","content":"'$PNG'","mimeType":"image/png"}'
R=$(curl_api -H "Authorization: Bearer $TTOK" -X POST $BASE/api/patients/1/photos -H "Content-Type: application/json" -d "$JBODY_BAD")
if echo "$R" | grep -q "422\|regex"; then ok "Path traversal rejected"; else ko "path traversal" "$R" | head -c 200; fi

# --- 5. INVENTORY ---
echo ""
echo "[5/6] INVENTORY + movements"
R=$(curl_api -H "Authorization: Bearer $GTOK" -X POST $BASE/api/inventory/purchases -H "Content-Type: application/json" -d '{"produk_id":1,"kode_batch":"BATCH-FINAL-001","qty":50,"hpp":75000,"supplier":"PT Final","kadaluarsa":"2027-12-31"}')
if echo "$R" | grep -q "201\|created\|success\|batch"; then ok "Add purchase"; else ko "add purchase" "$R" | head -c 200; fi

# Bad product
R=$(curl_api -H "Authorization: Bearer $GTOK" -X POST $BASE/api/inventory/purchases -H "Content-Type: application/json" -d '{"produk_id":99999,"kode_batch":"X","qty":1,"hpp":1,"supplier":"X","kadaluarsa":"2027-01-01"}')
if echo "$R" | grep -q "422\|not found"; then ok "Bad produk_id rejected"; else ko "bad produk_id" "$R" | head -c 200; fi

# qty 0
R=$(curl_api -H "Authorization: Bearer $GTOK" -X POST $BASE/api/inventory/purchases -H "Content-Type: application/json" -d '{"produk_id":1,"kode_batch":"X","qty":0,"hpp":1,"supplier":"X","kadaluarsa":"2027-01-01"}')
if echo "$R" | grep -q "422\|qty"; then ok "qty=0 rejected"; else ko "qty=0" "$R" | head -c 200; fi

# Movements
R=$(curl_api -H "Authorization: Bearer $GTOK" "$BASE/api/inventory-movements?from=2026-06-01&to=2026-06-30")
CNT=$(echo "$R" | grep -o '"count":[0-9]*' | head -1 | cut -d: -f2)
if [ -n "$CNT" ] && [ "$CNT" -gt 0 ]; then ok "Movements returns $CNT rows"; else ko "movements empty" "$R" | head -c 200; fi

# Bad date
R=$(curl_api -H "Authorization: Bearer $GTOK" "$BASE/api/inventory-movements?from=invalid&to=2026-06-30")
if echo "$R" | grep -q "422\|validasi\|date"; then ok "Invalid date rejected"; else ko "bad date" "$R" | head -c 200; fi

# --- 6. REPORTS ---
echo ""
echo "[6/6] REPORTS (Manajer only)"
curl -s -H "Host: $HOST" -H "Authorization: Bearer $MTOK" -o /tmp/daily.pdf -w "    Daily Report PDF: HTTP=%{http_code} size=%{size_download} type=%{content_type}\n" $BASE/api/daily-reports/2026-06-07/export
[ -s /tmp/daily.pdf ] && file /tmp/daily.pdf 2>&1 | head -1
curl -s -H "Host: $HOST" -H "Authorization: Bearer $MTOK" -o /tmp/stock.xlsx -w "    Stock XLSX: HTTP=%{http_code} size=%{size_download}\n" $BASE/api/reports/stock/export
[ -s /tmp/stock.xlsx ] && file /tmp/stock.xlsx 2>&1 | head -1
curl -s -H "Host: $HOST" -H "Authorization: Bearer $MTOK" -o /tmp/comm.xlsx -w "    Commission XLSX: HTTP=%{http_code} size=%{size_download}\n" $BASE/api/reports/commission/export
[ -s /tmp/comm.xlsx ] && file /tmp/comm.xlsx 2>&1 | head -1
curl -s -H "Host: $HOST" -H "Authorization: Bearer $MTOK" -o /tmp/finance.pdf -w "    Finance PDF: HTTP=%{http_code} size=%{size_download}\n" $BASE/api/reports/finance/export
[ -s /tmp/finance.pdf ] && file /tmp/finance.pdf 2>&1 | head -1

echo ""
echo "=========================================="
echo "FINAL RESULTS: $PASS pass, $FAIL fail"
echo "=========================================="
[ "$FAIL" -eq 0 ] && echo "ALL GREEN ✓" || echo "FAILED:$FAIL_LIST"
exit $FAIL
