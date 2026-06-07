#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token")
# Get finance report
curl -sS "http://127.0.0.1:8000/api/reports/finance/export" -H "Authorization: Bearer $TOKEN" -o "D:/users/stefa/project/sim-kk/.workflow/bbox/finance2.pdf"
# Extract first 500 chars of PDF (text strings)
strings "D:/users/stefa/project/sim-kk/.workflow/bbox/finance2.pdf" 2>/dev/null | head -40 || head -c 500 "D:/users/stefa/project/sim-kk/.workflow/bbox/finance2.pdf" | tr -d '\0' | head -c 500
echo ""
# Try pdftotext if installed
which pdftotext && pdftotext "D:/users/stefa/project/sim-kk/.workflow/bbox/finance2.pdf" - | head -30
