#!/bin/bash
# DEEP E2E: comprehensive testing across all 5 categories
# Returns: pass/fail count per category
set +e
BASE="http://43.133.142.74"
PASS=0
FAIL=0
declare -A RESULTS

run_test() {
    local name="$1"
    local cmd="$2"
    local expect="$3"
    local result
    result=$(eval "$cmd" 2>&1)
    local exit_code=$?
    if echo "$result" | grep -q "$expect" && [ $exit_code -eq 0 ]; then
        echo "✓ PASS: $name"
        PASS=$((PASS+1))
    else
        echo "✗ FAIL: $name (exit=$exit_code)"
        echo "  result: $result" | head -c 500
        echo ""
        FAIL=$((FAIL+1))
    fi
}

echo "=========================================="
echo "DEEP E2E SUITE — 7 categories, 30+ tests"
echo "=========================================="
echo ""

# 1. LOGIN — all 4 roles
echo "[1/7] LOGIN all roles"
for role in kasir terapis gudang manajer; do
  echo "  Testing $role..."
  RESP=$(curl -s -X POST $BASE/api/login -H "Content-Type: application/json" \
    -d "{\"username\":\"$role\",\"password\":\"simkk-2026\",\"level\":\"$(echo $role | sed 's/./\U&/g' | sed 's/^./\U&/g')\"}" 2>&1)
  TOKEN=$(echo "$RESP" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["token"]??"";' 2>/dev/null)
  if [ -n "$TOKEN" ]; then
    echo "    ✓ $role login OK (token=${#TOKEN} chars)"
    PASS=$((PASS+1))
  else
    echo "    ✗ $role login FAILED: $RESP" | head -c 200
    FAIL=$((FAIL+1))
  fi
done

# Wrong password
echo "  Testing wrong password..."
RESP=$(curl -s -X POST $BASE/api/login -H "Content-Type: application/json" \
  -d '{"username":"kasir","password":"wrong","level":"Kasir"}')
if echo "$RESP" | grep -q "401\|message"; then
  echo "    ✓ wrong password rejected"
  PASS=$((PASS+1))
else
  echo "    ✗ wrong password NOT rejected: $RESP"
  FAIL=$((FAIL+1))
fi

echo ""

# 2. AUTH + RBAC
echo "[2/7] AUTH + RBAC enforcement"
KTOKEN=$(curl -s -X POST $BASE/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' | php -r 'echo json_decode(stream_get_contents(STDIN),true)["token"]??"";')

# Kasir hit Gudang endpoint
RESP=$(curl -s -w "HTTP=%{http_code}" -X POST $BASE/api/inventory/purchases -H "Content-Type: application/json" -H "Authorization: Bearer $KTOKEN" -d '{"produk_id":1,"kode_batch":"X","qty":1,"hpp":1000,"supplier":"X","kadaluarsa":"2027-01-01"}')
if echo "$RESP" | grep -q "403\|role"; then
  echo "    ✓ Kasir blocked from Gudang endpoint"
  PASS=$((PASS+1))
else
  echo "    ✗ Kasir NOT blocked from Gudang: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

# No token
RESP=$(curl -s -w "HTTP=%{http_code}" $BASE/api/bootstrap)
if echo "$RESP" | grep -q "401\|Unauthorized\|token"; then
  echo "    ✓ No token rejected"
  PASS=$((PASS+1))
else
  echo "    ✗ No token NOT rejected: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

# Kasir hit Manajer-only report
RESP=$(curl -s -w "HTTP=%{http_code}" $BASE/api/daily-reports/2026-06-07/export -H "Authorization: Bearer $KTOKEN")
if echo "$RESP" | grep -q "403\|role"; then
  echo "    ✓ Kasir blocked from Daily Report"
  PASS=$((PASS+1))
else
  echo "    ✗ Kasir NOT blocked from Daily Report: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

echo ""

# 3. POS TRANSAKSI
echo "[3/7] POS TRANSAKSI flow"
MTOKEN=$(curl -s -X POST $BASE/api/login -H "Content-Type: application/json" -d '{"username":"manajer","password":"simkk-2026","level":"Manajer"}' | php -r 'echo json_decode(stream_get_contents(STDIN),true)["token"]??"";')

# Bootstrap should have inventory
RESP=$(curl -s $BASE/api/bootstrap -H "Authorization: Bearer $MTOKEN")
INV_COUNT=$(echo "$RESP" | php -r 'echo count(json_decode(stream_get_contents(STDIN),true)["inventory"]??[]);' 2>/dev/null)
if [ "$INV_COUNT" -gt 0 ]; then
  echo "    ✓ Bootstrap returns $INV_COUNT inventory items"
  PASS=$((PASS+1))
else
  echo "    ✗ No inventory in bootstrap: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

# POS pay with valid service (Facial Basic id=1 from seeder)
RESP=$(curl -s -w "HTTP=%{http_code}" -X POST $BASE/api/transactions/pay -H "Content-Type: application/json" -H "Authorization: Bearer $KTOKEN" -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}],"discount":0,"metode_bayar":"Tunai"}')
if echo "$RESP" | grep -q '"id":"TRX'; then
  TRX_ID=$(echo "$RESP" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["transaction"]["id"]??"";' 2>/dev/null)
  echo "    ✓ POS pay success: $TRX_ID"
  PASS=$((PASS+1))
else
  echo "    ✗ POS pay FAILED: $RESP" | head -c 300
  FAIL=$((FAIL+1))
fi

# Pay with insufficient stock (qty 9999)
RESP=$(curl -s -w "HTTP=%{http_code}" -X POST $BASE/api/transactions/pay -H "Content-Type: application/json" -H "Authorization: Bearer $KTOKEN" -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":9999}],"discount":0,"metode_bayar":"Tunai"}')
if echo "$RESP" | grep -q "422\|tidak cukup\|insufficient"; then
  echo "    ✓ Insufficient stock rejected with 422"
  PASS=$((PASS+1))
else
  echo "    ✗ Insufficient stock NOT rejected: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

# Pay with bad serviceId
RESP=$(curl -s -w "HTTP=%{http_code}" -X POST $BASE/api/transactions/pay -H "Content-Type: application/json" -H "Authorization: Bearer $KTOKEN" -d '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":99999,"qty":1}],"discount":0,"metode_bayar":"Tunai"}')
if echo "$RESP" | grep -q "422\|not found"; then
  echo "    ✓ Bad serviceId rejected"
  PASS=$((PASS+1))
else
  echo "    ✗ Bad serviceId NOT rejected: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

echo ""

# 4. REKAM MEDIS + FOTO
echo "[4/7] REKAM MEDIS + FOTO"
TTOKEN=$(curl -s -X POST $BASE/api/login -H "Content-Type: application/json" -d '{"username":"terapis","password":"simkk-2026","level":"Terapis"}' | php -r 'echo json_decode(stream_get_contents(STDIN),true)["token"]??"";')

# Add treatment (terapis->own pasien=1)
RESP=$(curl -s -w "HTTP=%{http_code}" -X POST $BASE/api/patients/1/treatments -H "Content-Type: application/json" -H "Authorization: Bearer $TTOKEN" -d '{"judul":"Test treatment","catatan":"Catatan terapi test"}')
if echo "$RESP" | grep -q '"id":\|"message"'; then
  echo "    ✓ Add treatment to own pasien: $(echo $RESP | head -c 100)"
  PASS=$((PASS+1))
else
  echo "    ✗ Add treatment FAILED: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

# Upload foto (terapis->own)
PNG_DATA_URL="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAAAAADhZOFXAAAADklEQVR4nGP4DwUMlDEA98A/wbI0QbsAAAAASUVORK5CYII="
JSON_BODY=$(php -r "echo json_encode(['label'=>'After','filename'=>'test-e2e.png','content'=>'$PNG_DATA_URL','mimeType'=>'image/png']);")
RESP=$(curl -s -w "HTTP=%{http_code}" -X POST $BASE/api/patients/1/photos -H "Content-Type: application/json" -H "Authorization: Bearer $TTOKEN" -d "$JSON_BODY")
if echo "$RESP" | grep -q '"id":\|"objectRef"'; then
  echo "    ✓ Upload foto to R2: $(echo $RESP | head -c 100)"
  PASS=$((PASS+1))
else
  echo "    ✗ Upload foto FAILED: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

# Try upload to OTHER pasien (should 403)
RESP=$(curl -s -w "HTTP=%{http_code}" -X POST $BASE/api/patients/2/photos -H "Content-Type: application/json" -H "Authorization: Bearer $TTOKEN" -d "$JSON_BODY")
if echo "$RESP" | grep -q "403\|bukan pasien"; then
  echo "    ✓ Terapis blocked from other pasien photo (ownership)"
  PASS=$((PASS+1))
else
  echo "    ✗ Terapis NOT blocked from other pasien: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

# Bad filename (path traversal)
RESP=$(curl -s -w "HTTP=%{http_code}" -X POST $BASE/api/patients/1/photos -H "Content-Type: application/json" -H "Authorization: Bearer $TTOKEN" -d "{\"label\":\"After\",\"filename\":\"../../../etc/passwd\",\"content\":\"$PNG_DATA_URL\",\"mimeType\":\"image/png\"}")
if echo "$RESP" | grep -q "422\|regex\|filename"; then
  echo "    ✓ Path traversal rejected"
  PASS=$((PASS+1))
else
  echo "    ✗ Path traversal NOT rejected: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

echo ""

# 5. INVENTORY
echo "[5/7] INVENTORY + MOVEMENTS"
GTOKEN=$(curl -s -X POST $BASE/api/login -H "Content-Type: application/json" -d '{"username":"gudang","password":"simkk-2026","level":"Gudang"}' | php -r 'echo json_decode(stream_get_contents(STDIN),true)["token"]??"";')

# Add purchase
RESP=$(curl -s -w "HTTP=%{http_code}" -X POST $BASE/api/inventory/purchases -H "Content-Type: application/json" -H "Authorization: Bearer $GTOKEN" -d '{"produk_id":1,"kode_batch":"BATCH-E2E-001","qty":50,"hpp":75000,"supplier":"PT Test E2E","kadaluarsa":"2027-12-31"}')
if echo "$RESP" | grep -q "201\|created\|success\|batch"; then
  echo "    ✓ Add purchase success"
  PASS=$((PASS+1))
else
  echo "    ✗ Add purchase FAILED: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

# Add purchase bad product
RESP=$(curl -s -w "HTTP=%{http_code}" -X POST $BASE/api/inventory/purchases -H "Content-Type: application/json" -H "Authorization: Bearer $GTOKEN" -d '{"produk_id":99999,"kode_batch":"X","qty":1,"hpp":1,"supplier":"X","kadaluarsa":"2027-01-01"}')
if echo "$RESP" | grep -q "422\|not found\|validasi"; then
  echo "    ✓ Bad product_id rejected"
  PASS=$((PASS+1))
else
  echo "    ✗ Bad product_id NOT rejected: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

# Add purchase qty 0
RESP=$(curl -s -w "HTTP=%{http_code}" -X POST $BASE/api/inventory/purchases -H "Content-Type: application/json" -H "Authorization: Bearer $GTOKEN" -d '{"produk_id":1,"kode_batch":"X","qty":0,"hpp":1,"supplier":"X","kadaluarsa":"2027-01-01"}')
if echo "$RESP" | grep -q "422\|validasi\|qty"; then
  echo "    ✓ qty=0 rejected"
  PASS=$((PASS+1))
else
  echo "    ✗ qty=0 NOT rejected: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

# Inventory movements
RESP=$(curl -s "$BASE/api/inventory-movements?from=2026-06-01&to=2026-06-30" -H "Authorization: Bearer $GTOKEN")
COUNT=$(echo "$RESP" | php -r 'echo json_decode(stream_get_contents(STDIN),true)["count"]??0;')
if [ "$COUNT" -gt 0 ]; then
  echo "    ✓ Inventory movements returns $COUNT rows"
  PASS=$((PASS+1))
else
  echo "    ✗ Inventory movements empty: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

# Invalid date range
RESP=$(curl -s -w "HTTP=%{http_code}" "$BASE/api/inventory-movements?from=invalid&to=2026-06-30" -H "Authorization: Bearer $GTOKEN")
if echo "$RESP" | grep -q "422\|validasi\|date"; then
  echo "    ✓ Invalid date rejected"
  PASS=$((PASS+1))
else
  echo "    ✗ Invalid date NOT rejected: $RESP" | head -c 200
  FAIL=$((FAIL+1))
fi

echo ""

# 6. REPORTS
echo "[6/7] REPORTS"
# Daily report
curl -s -o /tmp/daily.pdf -w "  Daily Report PDF: HTTP=%{http_code} size=%{size_download}\n" $BASE/api/daily-reports/2026-06-07/export -H "Authorization: Bearer $MTOKEN"
file /tmp/daily.pdf 2>&1 | head -1

# Stock export
curl -s -o /tmp/stock.xlsx -w "  Stock XLSX: HTTP=%{http_code} size=%{size_download}\n" $BASE/api/reports/stock/export -H "Authorization: Bearer $MTOKEN"
file /tmp/stock.xlsx 2>&1 | head -1

# Commission export
curl -s -o /tmp/comm.xlsx -w "  Commission XLSX: HTTP=%{http_code} size=%{size_download}\n" $BASE/api/reports/commission/export -H "Authorization: Bearer $MTOKEN"
file /tmp/comm.xlsx 2>&1 | head -1

# Finance export
curl -s -o /tmp/finance.pdf -w "  Finance PDF: HTTP=%{http_code} size=%{size_download}\n" $BASE/api/reports/finance/export -H "Authorization: Bearer $MTOKEN"
file /tmp/finance.pdf 2>&1 | head -1

echo ""

# 7. FRONTEND
echo "[7/7] FRONTEND SPA"
curl -s -o /dev/null -w "  GET /: HTTP=%{http_code} type=%{content_type}\n" $BASE/
curl -s -o /dev/null -w "  GET /assets/*: HTTP=%{http_code} type=%{content_type}\n" $BASE/assets/index-BFXZ17Zt.js
curl -s -o /dev/null -w "  GET /monitor/: HTTP=%{http_code}\n" $BASE/monitor/

echo ""
echo "=========================================="
echo "RESULTS: $PASS pass, $FAIL fail"
echo "=========================================="
exit $FAIL
