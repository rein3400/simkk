#!/bin/bash
TOKEN_K=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).kasir.token")
TOKEN_T=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).terapis.token")
TOKEN_G=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).gudang.token")
TOKEN_M=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token")

# Try /api/services etc
for p in /api/services /api/therapists /api/pasien /api/users /api/dashboard /api/finance /api/finance/export /api/cash-ledger /api/cash_ledger /api/commission; do
  for tk_name in kasir terapis gudang manajer; do
    T=$(eval echo \$TOKEN_$(echo $tk_name | head -c 1 | tr a-z A-Z))
  done
done

# Concise: just hit a few with kasir
for p in /api/services /api/therapists /api/pasien /api/users /api/dashboard /api/cash-ledger /api/cash_ledger /api/laporan; do
  STATUS=$(curl -sS -o /dev/null -w "%{http_code}" -H "Authorization: Bearer $TOKEN_K" "http://127.0.0.1:8000$p")
  echo "GET $p (kasir): $STATUS"
done

# POST /api/auth/logout
for tk in $TOKEN_K $TOKEN_T $TOKEN_G $TOKEN_M; do
  STATUS=$(curl -sS -o /dev/null -w "%{http_code}" -X POST -H "Authorization: Bearer $tk" -H "Content-Type: application/json" -d '{}' "http://127.0.0.1:8000/api/logout")
  echo "POST /api/logout: $STATUS"
done
