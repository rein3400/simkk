#!/bin/bash
TK=$(cat /tmp/kasir_tk)
# Test all methods on /api/transactions/pay
for m in GET PUT PATCH DELETE OPTIONS; do
  STATUS=$(curl -sS -o /dev/null -w "%{http_code}" -X $m -H "Authorization: Bearer $TK" "http://127.0.0.1:8000/api/transactions/pay")
  echo "$m /api/transactions/pay: $STATUS"
done

# Test OPTIONS /api/bootstrap
for m in GET PUT PATCH DELETE; do
  STATUS=$(curl -sS -o /dev/null -w "%{http_code}" -X $m -H "Authorization: Bearer $TK" "http://127.0.0.1:8000/api/bootstrap")
  echo "$m /api/bootstrap: $STATUS"
done

# Trace from invoice number? Test GET /api/transactions/TRX-...
for p in /api/transactions/TRX-260604-019 /api/transactions/RCPT-TRX-260604-019 /api/transactions/999; do
  STATUS=$(curl -sS -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TK" "http://127.0.0.1:8000$p")
  echo "GET $p: $STATUS"
done
