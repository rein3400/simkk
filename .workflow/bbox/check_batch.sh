#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token")
curl -sS "http://127.0.0.1:8000/api/bootstrap" -H "Authorization: Bearer $TOKEN" > /tmp/bs2.json
powershell -NoProfile -Command "
\$bs = Get-Content '/tmp/bs2.json' | ConvertFrom-Json
foreach (\$p in \$bs.inventory) {
  if (\$p.id -eq 1) {
    Write-Host '=== Barrier Serum ==='
    Write-Host \"totalStock: \$(\$p.totalStock)\"
    foreach (\$b in \$p.batches) {
      Write-Host \"  - \$(\$b.code) qty=\$(\$b.qty) firstOut=\$(\$b.firstOut) expiry=\$(\$b.expiry) supplier=\$(\$b.supplier)\"
    }
  }
}
"
