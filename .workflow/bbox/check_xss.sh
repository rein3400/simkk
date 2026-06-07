#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token")
curl -sS "http://127.0.0.1:8000/api/bootstrap" -H "Authorization: Bearer $TOKEN" > "D:/users/stefa/project/sim-kk/.workflow/bbox/bs_xss.json"
powershell -NoProfile -Command "
\$bs = Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/bs_xss.json' | ConvertFrom-Json
foreach (\$t in \$bs.transactions) {
  if (\$t.id -eq 'TRX-260604-020') {
    Write-Host \"Found TRX-260604-020: paymentMethod=\$(\$t.paymentMethod) catatan_field? \"
  }
}
# Now also check if there is catatan in transaction. Check service for payment_method with xss
\$xss = \$bs.transactions | Where-Object { \$_.paymentMethod -match 'script' } | Select-Object -First 3
\$xss | Format-List id, paymentMethod
"
