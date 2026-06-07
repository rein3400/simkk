#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).terapis.token")
URL="http://127.0.0.1:8000/api/patients/1/photos"
cd "D:/users/stefa/project/sim-kk/.workflow/bbox"

B64_PNG=$(base64 -w 0 test.png)
echo "--- png_b64 ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "label=Before" -F "filename=test.png" -F "content=$B64_PNG" "$URL"

B64_PHP=$(base64 -w 0 shell.jpg)
echo "--- php_b64 ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "label=Before" -F "filename=shell.jpg" -F "content=$B64_PHP" "$URL"

B64_TXT=$(base64 -w 0 notes.txt)
echo "--- txt_b64 ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "label=Before" -F "filename=notes.txt" -F "content=$B64_TXT" "$URL"

B64_SVG=$(base64 -w 0 x.svg)
echo "--- svg_b64 ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "label=Before" -F "filename=x.svg" -F "content=$B64_SVG" "$URL"

B64_BIG=$(base64 -w 0 big.png)
echo "--- big_1mb_b64 ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "label=Before" -F "filename=big.png" -F "content=$B64_BIG" "$URL"
