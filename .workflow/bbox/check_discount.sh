#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token")
curl -sS "http://127.0.0.1:8000/api/bootstrap" -H "Authorization: Bearer $TOKEN" > "D:/users/stefa/project/sim-kk/.workflow/bbox/bs_disc.json"
powershell -NoProfile -Command "
\$bs = Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/bs_disc.json' | ConvertFrom-Json
# 031 was neg discount, 032 was huge discount
foreach (\$id in 'TRX-260604-030','TRX-260604-031','TRX-260604-032') {
  \$t = \$bs.transactions | Where-Object { \$_.id -eq \$id }
  if (\$t) { Write-Host \"\$id subtotal=\$(\$t.subtotal) discount=\$(\$t.discount) total=\$(\$t.total) commission=\$(\$t.commission)\" }
}
"
