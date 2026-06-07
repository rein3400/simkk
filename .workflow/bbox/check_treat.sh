#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token")
curl -sS "http://127.0.0.1:8000/api/bootstrap" -H "Authorization: Bearer $TOKEN" > "D:/users/stefa/project/sim-kk/.workflow/bbox/bs_treat.json"
powershell -NoProfile -Command "
\$bs = Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/bs_treat.json' | ConvertFrom-Json
\$p1 = \$bs.patients | Where-Object { \$_.id -eq 1 }
Write-Host 'Treatments for patient 1:'
foreach (\$t in \$p1.treatments) {
  Write-Host \"  id=\$(\$t.id) date=\$(\$t.date) title=\$(\$t.title) notes-len=\$(\$t.notes.Length)\"
}
Write-Host 'Photos for patient 1:'
foreach (\$p in \$p1.photos) {
  Write-Host \"  id=\$(\$p.id) label=\$(\$p.label) date=\$(\$p.date) objectRef=\$(\$p.objectRef)\"
}
"
