#!/bin/bash
HOST="api.sim-kk.example.id"
BASE="http://43.133.142.74"
MTOK=$(curl -s -X POST -H "Host: $HOST" -H "Content-Type: application/json" -d '{"username":"manajer","password":"simkk-2026","level":"Manajer"}' $BASE/api/login | grep -o '"token":"[^"]*"' | head -1 | cut -d'"' -f4)
echo "Got Manajer token: ${#MTOK} chars"
echo ""
echo "=== Reports test ==="
curl -s -H "Host: $HOST" -H "Authorization: Bearer $MTOK" -o /tmp/daily.pdf -w "Daily PDF: HTTP=%{http_code} size=%{size_download}\n" $BASE/api/daily-reports/2026-06-07/export
file /tmp/daily.pdf 2>&1 | head -1
echo ""
curl -s -H "Host: $HOST" -H "Authorization: Bearer $MTOK" -o /tmp/stock.xlsx -w "Stock XLSX: HTTP=%{http_code} size=%{size_download}\n" $BASE/api/reports/stock/export
file /tmp/stock.xlsx 2>&1 | head -1
echo ""
curl -s -H "Host: $HOST" -H "Authorization: Bearer $MTOK" -o /tmp/comm.xlsx -w "Commission XLSX: HTTP=%{http_code} size=%{size_download}\n" $BASE/api/reports/commission/export
file /tmp/comm.xlsx 2>&1 | head -1
echo ""
curl -s -H "Host: $HOST" -H "Authorization: Bearer $MTOK" -o /tmp/finance.pdf -w "Finance PDF: HTTP=%{http_code} size=%{size_download}\n" $BASE/api/reports/finance/export
file /tmp/finance.pdf 2>&1 | head -1
