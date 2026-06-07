#!/bin/bash
HOST="api.sim-kk.example.id"
BASE="http://43.133.142.74"
MTOK=$(curl -s -X POST -H "Host: $HOST" -H "Content-Type: application/json" -d '{"username":"manajer","password":"simkk-2026","level":"Manajer"}' $BASE/api/login | grep -o '"token":"[^"]*"' | head -1 | cut -d'"' -f4)
echo "=== Daily report HEAD + full headers ==="
curl -s -H "Host: $HOST" -H "Authorization: Bearer $MTOK" -D /tmp/h.txt -o /tmp/d.pdf $BASE/api/daily-reports/2026-06-07/export
cat /tmp/h.txt
echo "---"
echo "Body size: $(stat -c %s /tmp/d.pdf)"
xxd /tmp/d.pdf 2>/dev/null | head -5
