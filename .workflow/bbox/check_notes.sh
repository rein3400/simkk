#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token")
curl -sS "http://127.0.0.1:8000/api/bootstrap" -H "Authorization: Bearer $TOKEN" > "D:/users/stefa/project/sim-kk/.workflow/bbox/bs_full.json"
powershell -NoProfile -Command "
\$bs = Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/bs_full.json' | ConvertFrom-Json
# Look at TRX-260604-029 which had 1mb catatan
\$t = \$bs.transactions | Where-Object { \$_.id -eq 'TRX-260604-029' }
if (\$t) { \$t | Format-List id, paymentMethod, total, commission }
else { Write-Host 'not found' }
# Check transaction count
Write-Host \"Total tx: \$(\$bs.transactions.Count)\"
"
