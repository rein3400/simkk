#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).terapis.token")
URL="http://127.0.0.1:8000/api/patients/1/photos"

# Try via JSON body with base64 content
cd "D:/users/stefa/project/sim-kk/.workflow/bbox"
B64_BIG=$(base64 -w 0 big.png)

JSON=$(printf '{"label":"Before","filename":"big.png","content":"%s"}' "$B64_BIG")
echo "--- big_1mb_json ---"
echo "$JSON" | curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" --data-binary @- "$URL" | head -c 500
echo ""

B64_PHP=$(base64 -w 0 shell.jpg)
JSON=$(printf '{"label":"Before","filename":"shell.php","content":"%s"}' "$B64_PHP")
echo "--- php_renamed_php ---"
echo "$JSON" | curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" --data-binary @- "$URL" | head -c 500
echo ""

# Try .exe
B64=$(base64 -w 0 shell.jpg)
JSON=$(printf '{"label":"Before","filename":"shell.exe","content":"%s"}' "$B64")
echo "--- exe ---"
echo "$JSON" | curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" --data-binary @- "$URL" | head -c 500
echo ""

# Try path traversal
B64=$(base64 -w 0 shell.jpg)
JSON=$(printf '{"label":"Before","filename":"../../../etc/passwd","content":"%s"}' "$B64")
echo "--- path_traversal ---"
echo "$JSON" | curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" --data-binary @- "$URL" | head -c 500
echo ""

# Empty content
JSON='{"label":"Before","filename":"empty.png","content":""}'
echo "--- empty_content ---"
echo "$JSON" | curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" --data-binary @- "$URL" | head -c 500
echo ""

# Huge base64 ~ 10MB
head -c 10000000 /dev/urandom > /tmp/r10.bin
B64=$(base64 -w 0 /tmp/r10.bin)
JSON=$(printf '{"label":"Before","filename":"huge.bin","content":"%s"}' "$B64")
echo "--- 10mb_random ---"
echo "$JSON" | curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" --data-binary @- "$URL" | head -c 500
echo ""
