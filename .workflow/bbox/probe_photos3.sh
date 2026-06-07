#!/bin/bash
TOKEN=$(powershell -NoProfile -Command "(Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).terapis.token")
echo "Token: $TOKEN"

URL="http://127.0.0.1:8000/api/patients/1/photos"

cd "D:/users/stefa/project/sim-kk/.workflow/bbox"

echo "--- png_real ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "label=Before" -F "file=@test.png" "$URL"

echo "--- php_renamed_jpg ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "label=Before" -F "file=@shell.jpg" "$URL"

echo "--- txt ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "label=Before" -F "file=@notes.txt" "$URL"

echo "--- svg ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "label=Before" -F "file=@x.svg" "$URL"

echo "--- big_1mb ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "label=Before" -F "file=@big.png" "$URL"

echo "--- empty_label ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "label=" -F "file=@test.png" "$URL"

echo "--- no_label_param ---"
curl -sS -w "\nSTATUS=%{http_code}\n" -X POST -H "Authorization: Bearer $TOKEN" \
  -F "file=@test.png" "$URL"
