#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token")

echo "--- finance ---"
curl -sS -o "D:/users/stefa/project/sim-kk/.workflow/bbox/finance.pdf" -w "STATUS=%{http_code} SIZE=%{size_download}\n" \
  "http://127.0.0.1:8000/api/reports/finance/export" -H "Authorization: Bearer $TOKEN"
# Check header
head -c 4 "D:/users/stefa/project/sim-kk/.workflow/bbox/finance.pdf" | xxd

echo "--- stock ---"
curl -sS -o "D:/users/stefa/project/sim-kk/.workflow/bbox/stock.xlsx" -w "STATUS=%{http_code} SIZE=%{size_download}\n" \
  "http://127.0.0.1:8000/api/reports/stock/export" -H "Authorization: Bearer $TOKEN"
head -c 4 "D:/users/stefa/project/sim-kk/.workflow/bbox/stock.xlsx" | xxd

echo "--- commission ---"
curl -sS -o "D:/users/stefa/project/sim-kk/.workflow/bbox/commission.xlsx" -w "STATUS=%{http_code} SIZE=%{size_download}\n" \
  "http://127.0.0.1:8000/api/reports/commission/export" -H "Authorization: Bearer $TOKEN"
head -c 4 "D:/users/stefa/project/sim-kk/.workflow/bbox/commission.xlsx" | xxd
