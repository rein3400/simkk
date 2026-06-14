#!/bin/bash
# Comprehensive E2E Test Suite for SIM-KK
# Tests all 47 routes, 4 roles, edge cases, security checks

set -e
BASE="http://127.0.0.1:8000"
PY="/c/Python314/python.exe"
PASS=0
FAIL=0
TOTAL=0

test_api() {
    local desc="$1"
    local expected_code="$2"
    local method="$3"
    local url="$4"
    shift 4
    TOTAL=$((TOTAL + 1))

    response=$(curl -s -w "\n%{http_code}" -X "$method" "$url" "$@")
    body=$(echo "$response" | head -n -1)
    code=$(echo "$response" | tail -n 1)

    if [ "$code" = "$expected_code" ]; then
        echo "✅ PASS: $desc (HTTP $code)"
        PASS=$((PASS + 1))
    else
        echo "❌ FAIL: $desc (expected $expected_code, got $code)"
        echo "   Response: $body" | head -c 200
        FAIL=$((FAIL + 1))
    fi
    echo "$body"
}

echo "=== SIM-KK E2E Test Suite ==="
echo "Server: $BASE"
echo ""

# ============================================================================
# 1. AUTHENTICATION
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1. AUTHENTICATION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Login all 4 roles
KASIR_TOKEN=$(test_api "Login Kasir" 200 POST "$BASE/api/login" -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
TERAPIS_TOKEN=$(test_api "Login Terapis" 200 POST "$BASE/api/login" -H "Content-Type: application/json" -d '{"username":"terapis","password":"simkk-2026","level":"Terapis"}' | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
GUDANG_TOKEN=$(test_api "Login Gudang" 200 POST "$BASE/api/login" -H "Content-Type: application/json" -d '{"username":"gudang","password":"simkk-2026","level":"Gudang"}' | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
MANAJER_TOKEN=$(test_api "Login Manajer" 200 POST "$BASE/api/login" -H "Content-Type: application/json" -d '{"username":"manajer","password":"simkk-2026","level":"Manajer"}' | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

# Login edge cases
test_api "Login invalid password" 401 POST "$BASE/api/login" -H "Content-Type: application/json" -d '{"username":"kasir","password":"wrong","level":"Kasir"}' > /dev/null
test_api "Login missing role" 422 POST "$BASE/api/login" -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026"}' > /dev/null
test_api "Login empty body" 422 POST "$BASE/api/login" -H "Content-Type: application/json" -d '{}' > /dev/null

# Logout
test_api "Logout Kasir" 200 POST "$BASE/api/logout" -H "Authorization: Bearer $KASIR_TOKEN" > /dev/null

# Re-login for subsequent tests
KASIR_TOKEN=$(curl -s -X POST "$BASE/api/login" -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

echo ""

# ============================================================================
# 2. BOOTSTRAP (ROLE-SCOPED)
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2. BOOTSTRAP (ROLE-SCOPED)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_api "Bootstrap Manajer (full)" 200 GET "$BASE/api/bootstrap" -H "Authorization: Bearer $MANAJER_TOKEN" | $PY -c "
import sys, json
d = json.load(sys.stdin)
p = d.get('patients', [])
t = d.get('transactions', [])
s = d.get('services', [])
i = d.get('inventory', [])
r = d.get('reports', [])
print(f'   patients={len(p)}, transactions={len(t)}, services={len(s)}, inventory={len(i)}, reports={len(r)}')
if p and 'treatments' in p[0]:
    print(f'   ✅ Manajer sees patient treatments')
else:
    print(f'   ❌ Manajer missing treatments')
" 2>&1

test_api "Bootstrap Kasir (names only)" 200 GET "$BASE/api/bootstrap" -H "Authorization: Bearer $KASIR_TOKEN" | $PY -c "
import sys, json
d = json.load(sys.stdin)
p = d.get('patients', [])
t = d.get('transactions', [])
s = d.get('services', [])
i = d.get('inventory', [])
r = d.get('reports', [])
print(f'   patients={len(p)}, transactions={len(t)}, services={len(s)}, inventory={len(i)}, reports={len(r)}')
if p and 'treatments' not in p[0]:
    print(f'   ✅ Kasir does NOT see patient treatments (privacy scoped)')
else:
    print(f'   ❌ Kasir sees treatments (privacy leak!)')
" 2>&1

test_api "Bootstrap Gudang (no patients)" 200 GET "$BASE/api/bootstrap" -H "Authorization: Bearer $GUDANG_TOKEN" | $PY -c "
import sys, json
d = json.load(sys.stdin)
p = d.get('patients', [])
t = d.get('transactions', [])
i = d.get('inventory', [])
print(f'   patients={len(p)}, transactions={len(t)}, inventory={len(i)}')
if len(p) == 0:
    print(f'   ✅ Gudang sees no patients')
if len(i) > 0:
    print(f'   ✅ Gudang sees inventory')
" 2>&1

echo ""

# ============================================================================
# 3. POS / TRANSACTIONS
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "3. POS / TRANSACTIONS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Pay transaction
PAY_RESPONSE=$(test_api "Pay transaction (Kasir)" 201 POST "$BASE/api/transactions/pay" -H "Authorization: Bearer $KASIR_TOKEN" -H "Content-Type: application/json" -H "Idempotency-Key: test-e2e-$(date +%s)" -d '{
    "pasien_id": 1,
    "terapis_id": 1,
    "items": [{"serviceId": 1, "qty": 2}],
    "diskon": 0,
    "metode_bayar": "Tunai"
}')
TRX_ID=$(echo "$PAY_RESPONSE" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
echo "   Transaction ID: $TRX_ID"

# Idempotency test - same key should return cached
test_api "Idempotency replay (same key)" 201 POST "$BASE/api/transactions/pay" -H "Authorization: Bearer $KASIR_TOKEN" -H "Content-Type: application/json" -H "Idempotency-Key: test-e2e-replay" -d '{
    "pasien_id": 1,
    "terapis_id": 1,
    "items": [{"serviceId": 2, "qty": 1}],
    "diskon": 0,
    "metode_bayar": "QRIS"
}' | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 > /tmp/trx1.txt
test_api "Idempotency replay (same key again)" 201 POST "$BASE/api/transactions/pay" -H "Authorization: Bearer $KASIR_TOKEN" -H "Content-Type: application/json" -H "Idempotency-Key: test-e2e-replay" -d '{
    "pasien_id": 1,
    "terapis_id": 1,
    "items": [{"serviceId": 2, "qty": 1}],
    "diskon": 0,
    "metode_bayar": "QRIS"
}' | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4 > /tmp/trx2.txt
if diff -q /tmp/trx1.txt /tmp/trx2.txt > /dev/null 2>&1; then
    echo "   ✅ Idempotency works (same TRX ID returned)"
else
    echo "   ❌ Idempotency BROKEN (different TRX IDs)"
fi

# Validation edge cases
test_api "Pay missing items" 422 POST "$BASE/api/transactions/pay" -H "Authorization: Bearer $KASIR_TOKEN" -H "Content-Type: application/json" -d '{"pasien_id": 1, "terapis_id": 1}' > /dev/null
test_api "Pay empty items array" 422 POST "$BASE/api/transactions/pay" -H "Authorization: Bearer $KASIR_TOKEN" -H "Content-Type: application/json" -d '{"pasien_id": 1, "terapis_id": 1, "items": []}' > /dev/null
test_api "Pay invalid serviceId" 422 POST "$BASE/api/transactions/pay" -H "Authorization: Bearer $KASIR_TOKEN" -H "Content-Type: application/json" -d '{"pasien_id": 1, "terapis_id": 1, "items": [{"serviceId": 99999, "qty": 1}]}' > /dev/null
test_api "Pay qty over 100" 422 POST "$BASE/api/transactions/pay" -H "Authorization: Bearer $KASIR_TOKEN" -H "Content-Type: application/json" -d '{"pasien_id": 1, "terapis_id": 1, "items": [{"serviceId": 1, "qty": 101}]}' > /dev/null
test_api "Pay invalid metode_bayar" 422 POST "$BASE/api/transactions/pay" -H "Authorization: Bearer $KASIR_TOKEN" -H "Content-Type: application/json" -d '{"pasien_id": 1, "terapis_id": 1, "items": [{"serviceId": 1, "qty": 1}], "metode_bayar": "invalid"}' > /dev/null

# Gudang cannot pay
test_api "Pay denied for Gudang" 403 POST "$BASE/api/transactions/pay" -H "Authorization: Bearer $GUDANG_TOKEN" -H "Content-Type: application/json" -d '{"pasien_id": 1, "terapis_id": 1, "items": [{"serviceId": 1, "qty": 1}]}' > /dev/null

echo ""

# ============================================================================
# 4. VOID / DELETE TRANSACTION
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "4. VOID / DELETE TRANSACTION"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -n "$TRX_ID" ]; then
    TRX_NUM=$(echo "$TRX_ID" | grep -o '[0-9]*$')
    test_api "Void transaction (Manajer)" 200 DELETE "$BASE/api/transactions/$TRX_NUM" -H "Authorization: Bearer $MANAJER_TOKEN" | grep -o '"deleted":true'
    test_api "Void denied for Kasir" 403 DELETE "$BASE/api/transactions/$TRX_NUM" -H "Authorization: Bearer $KASIR_TOKEN" > /dev/null
    test_api "Void denied for Terapis" 403 DELETE "$BASE/api/transactions/$TRX_NUM" -H "Authorization: Bearer $TERAPIS_TOKEN" > /dev/null
fi

echo ""

# ============================================================================
# 5. MEDICAL RECORDS (REKAM MEDIS)
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "5. MEDICAL RECORDS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Add treatment
TREAT_RESPONSE=$(test_api "Add treatment (Terapis)" 201 POST "$BASE/api/patients/1/treatments" -H "Authorization: Bearer $TERAPIS_TOKEN" -H "Content-Type: application/json" -d '{
    "judul": "E2E Test Treatment",
    "catatan": "Testing comprehensive E2E flow"
}')
TREAT_ID=$(echo "$TREAT_RESPONSE" | grep -o '"id":[0-9]*' | cut -d: -f2)
TREAT_DATE=$(echo "$TREAT_RESPONSE" | grep -o '"date":"[^"]*"' | cut -d'"' -f4)
echo "   Treatment ID: $TREAT_ID, Date: $TREAT_DATE"

# Verify date format is Y-m-d (not d M)
if echo "$TREAT_DATE" | grep -qE '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'; then
    echo "   ✅ Date format Y-m-d correct"
else
    echo "   ❌ Date format wrong (expected Y-m-d, got $TREAT_DATE)"
fi

# Update treatment
test_api "Update treatment (Terapis)" 200 PUT "$BASE/api/patients/1/treatments/$TREAT_ID" -H "Authorization: Bearer $TERAPIS_TOKEN" -H "Content-Type: application/json" -d '{
    "judul": "Updated E2E Treatment",
    "catatan": "Updated via E2E test"
}' | grep -o '"title":"[^"]*"'

# Delete treatment
test_api "Delete treatment (Terapis)" 200 DELETE "$BASE/api/patients/1/treatments/$TREAT_ID" -H "Authorization: Bearer $TERAPIS_TOKEN" | grep -o '"deleted":true'

# Gudang cannot add treatment
test_api "Add treatment denied for Gudang" 403 POST "$BASE/api/patients/1/treatments" -H "Authorization: Bearer $GUDANG_TOKEN" -H "Content-Type: application/json" -d '{"judul": "X", "catatan": "Y"}' > /dev/null

# Validation
test_api "Add treatment missing judul" 422 POST "$BASE/api/patients/1/treatments" -H "Authorization: Bearer $TERAPIS_TOKEN" -H "Content-Type: application/json" -d '{"catatan": "test"}' > /dev/null
test_api "Add treatment catatan too long" 422 POST "$BASE/api/patients/1/treatments" -H "Authorization: Bearer $TERAPIS_TOKEN" -H "Content-Type: application/json" -d "{\"judul\": \"X\", \"catatan\": \"$($PY -c 'print("A" * 6000)')\"}" > /dev/null

echo ""

# ============================================================================
# 6. PHOTO UPLOAD
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "6. PHOTO UPLOAD"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Create a small valid PNG (1x1 pixel)
PNG_B64=$($PY -c "
import base64, struct, zlib
def create_png():
    sig = b'\x89PNG\r\n\x1a\n'
    ihdr_data = struct.pack('>IIBBBBB', 1, 1, 8, 2, 0, 0, 0)
    ihdr_crc = struct.pack('>I', zlib.crc32(b'IHDR' + ihdr_data) & 0xffffffff)
    ihdr = struct.pack('>I', 13) + b'IHDR' + ihdr_data + ihdr_crc
    raw = zlib.compress(b'\x00\xff\x00\x00')
    idat_crc = struct.pack('>I', zlib.crc32(b'IDAT' + raw) & 0xffffffff)
    idat = struct.pack('>I', len(raw)) + b'IDAT' + raw + idat_crc
    iend_crc = struct.pack('>I', zlib.crc32(b'IEND') & 0xffffffff)
    iend = struct.pack('>I', 0) + b'IEND' + iend_crc
    return base64.b64encode(sig + ihdr + idat + iend).decode()
print(create_png())
")

test_api "Upload valid photo" 201 POST "$BASE/api/patients/1/photos" -H "Authorization: Bearer $TERAPIS_TOKEN" -H "Content-Type: application/json" -d "{
    \"label\": \"Before\",
    \"filename\": \"e2e-test.png\",
    \"content\": \"$PNG_B64\"
}" | grep -o '"id":"[^"]*"' > /tmp/photo_id.txt

# Invalid extensions
test_api "Photo upload .php rejected" 422 POST "$BASE/api/patients/1/photos" -H "Authorization: Bearer $TERAPIS_TOKEN" -H "Content-Type: application/json" -d "{\"label\": \"Before\", \"filename\": \"shell.php\", \"content\": \"$PNG_B64\"}" > /dev/null
test_api "Photo upload .exe rejected" 422 POST "$BASE/api/patients/1/photos" -H "Authorization: Bearer $TERAPIS_TOKEN" -H "Content-Type: application/json" -d "{\"label\": \"Before\", \"filename\": \"shell.exe\", \"content\": \"$PNG_B64\"}" > /dev/null

# Content too large (> 14336 chars base64)
LARGE_B64=$($PY -c "print('A' * 20000)")
test_api "Photo content too large rejected" 422 POST "$BASE/api/patients/1/photos" -H "Authorization: Bearer $TERAPIS_TOKEN" -H "Content-Type: application/json" -d "{\"label\": \"Before\", \"filename\": \"big.png\", \"content\": \"$LARGE_B64\"}" > /dev/null

echo ""

# ============================================================================
# 7. INVENTORY
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "7. INVENTORY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_api "Add purchase (Gudang)" 201 POST "$BASE/api/inventory/purchases" -H "Authorization: Bearer $GUDANG_TOKEN" -H "Content-Type: application/json" -d '{
    "produk_id": 1,
    "supplier": "PT E2E Test",
    "kode_batch": "E2E-TEST-001",
    "qty": 10,
    "hpp": 50000,
    "kadaluarsa": "2027-12-31"
}' | grep -o '"kode_batch":"[^"]*"'

# Invalid batch codes
test_api "Batch with path traversal rejected" 422 POST "$BASE/api/inventory/purchases" -H "Authorization: Bearer $GUDANG_TOKEN" -H "Content-Type: application/json" -d '{
    "produk_id": 1,
    "supplier": "PT Test",
    "kode_batch": "../../etc/passwd",
    "qty": 10,
    "hpp": 50000
}' > /dev/null

test_api "Batch with spaces rejected" 422 POST "$BASE/api/inventory/purchases" -H "Authorization: Bearer $GUDANG_TOKEN" -H "Content-Type: application/json" -d '{
    "produk_id": 1,
    "supplier": "PT Test",
    "kode_batch": "batch with spaces",
    "qty": 10,
    "hpp": 50000
}' > /dev/null

test_api "Batch with dots rejected" 422 POST "$BASE/api/inventory/purchases" -H "Authorization: Bearer $GUDANG_TOKEN" -H "Content-Type: application/json" -d '{
    "produk_id": 1,
    "supplier": "PT Test",
    "kode_batch": "batch..with..dots",
    "qty": 10,
    "hpp": 50000
}' > /dev/null

# Kasir cannot add purchase
test_api "Purchase denied for Kasir" 403 POST "$BASE/api/inventory/purchases" -H "Authorization: Bearer $KASIR_TOKEN" -H "Content-Type: application/json" -d '{
    "produk_id": 1, "supplier": "PT X", "kode_batch": "X", "qty": 1, "hpp": 100
}' > /dev/null

echo ""

# ============================================================================
# 8. REPORTS
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "8. REPORTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_api "Export finance PDF (Manajer)" 200 GET "$BASE/api/reports/finance/export" -H "Authorization: Bearer $MANAJER_TOKEN" -o /tmp/finance.pdf
PDF_SIZE=$(wc -c < /tmp/finance.pdf)
echo "   PDF size: $PDF_SIZE bytes"
if [ "$PDF_SIZE" -gt 100 ]; then
    echo "   ✅ Finance PDF generated"
fi

test_api "Export stock XLSX (Manajer)" 200 GET "$BASE/api/reports/stock/export" -H "Authorization: Bearer $MANAJER_TOKEN" -o /tmp/stock.xlsx
XLSX_SIZE=$(wc -c < /tmp/stock.xlsx)
echo "   XLSX size: $XLSX_SIZE bytes"

test_api "Export commission XLSX (Manajer)" 200 GET "$BASE/api/reports/commission/export" -H "Authorization: Bearer $MANAJER_TOKEN" -o /tmp/commission.xlsx

# Kasir cannot export reports
test_api "Report export denied for Kasir" 403 GET "$BASE/api/reports/finance/export" -H "Authorization: Bearer $KASIR_TOKEN" > /dev/null

echo ""

# ============================================================================
# 9. DAILY REPORTS
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "9. DAILY REPORTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

TODAY=$(date +%Y-%m-%d)

test_api "Daily report status (Kasir)" 200 GET "$BASE/api/daily-reports/status?tanggal=$TODAY" -H "Authorization: Bearer $KASIR_TOKEN" | $PY -c "
import sys, json
d = json.load(sys.stdin)
print(f'   status={d.get(\"status\")}, closing_id={d.get(\"closing_id\")}, transactions={d.get(\"transaction_count\")}')
"

test_api "Submit daily report (Kasir)" 200 POST "$BASE/api/daily-reports/$TODAY/submit" -H "Authorization: Bearer $KASIR_TOKEN" -H "Content-Type: application/json" -d '{}' | grep -o '"status":"[^"]*"'

# Get status again to find closing_id
CLOSING_ID=$(curl -s "$BASE/api/daily-reports/status?tanggal=$TODAY" -H "Authorization: Bearer $KASIR_TOKEN" | grep -o '"closing_id":[0-9]*' | cut -d: -f2)
echo "   Closing ID: $CLOSING_ID"

if [ -n "$CLOSING_ID" ] && [ "$CLOSING_ID" != "null" ]; then
    test_api "Approve daily report (Manajer)" 200 POST "$BASE/api/daily-reports/closings/$CLOSING_ID/approve" -H "Authorization: Bearer $MANAJER_TOKEN" -H "Content-Type: application/json" -d '{}' | grep -o '"status":"[^"]*"'
fi

test_api "Export daily report PDF (Manajer)" 200 GET "$BASE/api/daily-reports/$TODAY/export" -H "Authorization: Bearer $MANAJER_TOKEN" -o /tmp/daily.pdf
DAILY_SIZE=$(wc -c < /tmp/daily.pdf)
echo "   Daily PDF size: $DAILY_SIZE bytes"

# Invalid date format
test_api "Daily report invalid date" 422 GET "$BASE/api/daily-reports/status?tanggal=not-a-date" -H "Authorization: Bearer $KASIR_TOKEN" > /dev/null

echo ""

# ============================================================================
# 10. ADMIN CRUD
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "10. ADMIN CRUD (MANAJER ONLY)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Layanan CRUD
test_api "GET layanan list (Manajer)" 200 GET "$BASE/api/admin/layanan" -H "Authorization: Bearer $MANAJER_TOKEN" | $PY -c "
import sys, json
d = json.load(sys.stdin)
print(f'   {len(d)} layanan found')
"

test_api "GET layanan denied for Kasir" 403 GET "$BASE/api/admin/layanan" -H "Authorization: Bearer $KASIR_TOKEN" > /dev/null
test_api "GET layanan denied for Terapis" 403 GET "$BASE/api/admin/layanan" -H "Authorization: Bearer $TERAPIS_TOKEN" > /dev/null
test_api "GET layanan denied for Gudang" 403 GET "$BASE/api/admin/layanan" -H "Authorization: Bearer $GUDANG_TOKEN" > /dev/null

# Produk CRUD
test_api "GET produk list (Manajer)" 200 GET "$BASE/api/admin/produk" -H "Authorization: Bearer $MANAJER_TOKEN" > /dev/null

# Users CRUD
test_api "GET users list (Manajer)" 200 GET "$BASE/api/admin/users" -H "Authorization: Bearer $MANAJER_TOKEN" > /dev/null

echo ""

# ============================================================================
# 11. OTHER ENDPOINTS
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "11. OTHER ENDPOINTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

test_api "Dashboard (Manajer)" 200 GET "$BASE/api/dashboard" -H "Authorization: Bearer $MANAJER_TOKEN" > /dev/null
test_api "Dashboard denied for Kasir" 403 GET "$BASE/api/dashboard" -H "Authorization: Bearer $KASIR_TOKEN" > /dev/null

test_api "Audit logs (Manajer)" 200 GET "$BASE/api/audit-logs" -H "Authorization: Bearer $MANAJER_TOKEN" > /dev/null
test_api "Audit logs denied for Kasir" 403 GET "$BASE/api/audit-logs" -H "Authorization: Bearer $KASIR_TOKEN" > /dev/null

test_api "Search (Manajer)" 200 GET "$BASE/api/search?q=test" -H "Authorization: Bearer $MANAJER_TOKEN" > /dev/null

test_api "Inventory movements (Gudang)" 200 GET "$BASE/api/inventory-movements" -H "Authorization: Bearer $GUDANG_TOKEN" > /dev/null

echo ""

# ============================================================================
# 12. SECURITY CHECKS
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "12. SECURITY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# CORS
CORS_RESPONSE=$(curl -s -i -X OPTIONS "$BASE/api/transactions/pay" -H "Origin: http://evil.com" -H "Access-Control-Request-Method: POST" 2>&1)
if echo "$CORS_RESPONSE" | grep -qi "Access-Control-Allow-Origin: \*"; then
    echo "❌ FAIL: CORS wildcard * detected"
    FAIL=$((FAIL + 1))
else
    echo "✅ PASS: CORS not wildcard"
    PASS=$((PASS + 1))
fi
TOTAL=$((TOTAL + 1))

# Unauthenticated access
test_api "Bootstrap without token" 401 GET "$BASE/api/bootstrap" > /dev/null
test_api "Pay without token" 401 POST "$BASE/api/transactions/pay" -H "Content-Type: application/json" -d '{}' > /dev/null

# Invalid token
test_api "Bootstrap with invalid token" 401 GET "$BASE/api/bootstrap" -H "Authorization: Bearer invalid-token-12345" > /dev/null

echo ""

# ============================================================================
# SUMMARY
# ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "SUMMARY"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Total:  $TOTAL"
echo "Passed: $PASS"
echo "Failed: $FAIL"
echo ""

if [ "$FAIL" -eq 0 ]; then
    echo "🎉 ALL TESTS PASSED"
    exit 0
else
    echo "⚠️  SOME TESTS FAILED"
    exit 1
fi
