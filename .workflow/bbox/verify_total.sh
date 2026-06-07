#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token")
curl -sS "http://127.0.0.1:8000/api/bootstrap" -H "Authorization: Bearer $TOKEN" > "D:/users/stefa/project/sim-kk/.workflow/bbox/bs_final.json"
powershell -NoProfile -Command "
\$bs = Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/bs_final.json' | ConvertFrom-Json

# Total Transaksi
\$all = \$bs.transactions
Write-Host \"Total transaksi: \$(\$all.Count)\"
\$seed = (\$all | Where-Object { \$_.id -like 'TRX-2505-*' }).Count
\$mine = (\$all | Where-Object { \$_.id -like 'TRX-260604-*' }).Count
Write-Host \"Seed: \$seed, My: \$mine\"

# Total amount
\$myTotal = 0
foreach (\$t in \$all) {
  if (\$t.id -like 'TRX-260604-*') { \$myTotal += [int]\$t.total }
}
Write-Host \"Sum of my transaksi.total: \$myTotal\"

# Cash ledger - bootstrap doesn't expose cash-ledger, only inside reports
Write-Host \"Reports finance rows: \"
\$fin = \$bs.reports | Where-Object { \$_.id -eq 'finance' }
\$finLast = \$fin.rows | Select-Object -Last 1
Write-Host \"  Last finance row: \$(\$finLast | ConvertTo-Json -Compress)\"
"
