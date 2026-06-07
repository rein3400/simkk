#!/bin/bash
# E2E R2 test with data URL format
set -e
cd /var/www/sim-kk/apps/api

# Data URL format (decoder expects this prefix)
PNG_DATA_URL="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAAAAADhZOFXAAAADklEQVR4nGP4DwUMlDEA98A/wbI0QbsAAAAASUVORK5CYII="

# 1. Login Terapis
LOGIN=$(curl -s -X POST http://127.0.0.1/api/login -H "Content-Type: application/json" -d '{"username":"terapis","password":"simkk-2026","level":"Terapis"}')
TTOKEN=$(echo "$LOGIN" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["token"] ?? "";')
echo "Terapis token: ${#TTOKEN} chars"

# 2. Build JSON body properly
JSON_BODY=$(php -r '
$png = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAAAAADhZOFXAAAADklEQVR4nGP4DwUMlDEA98A/wbI0QbsAAAAASUVORK5CYII=";
echo json_encode([
    "label" => "After",
    "filename" => "test-r2.png",
    "content" => $png,
    "mimeType" => "image/png"
]);
')

# 3. Upload to R2
echo ""
echo "=== Upload photo to R2 ==="
RESP=$(curl -s -X POST http://127.0.0.1/api/patients/1/photos \
  -H "Authorization: Bearer $TTOKEN" \
  -H "Content-Type: application/json" \
  -d "$JSON_BODY")
echo "$RESP" | head -c 500

# 4. DB count
echo ""
echo ""
echo "=== DB count ==="
sudo -n -u deploy php /var/www/sim-kk/apps/api/artisan tinker --execute='echo \App\Models\FotoKlinis::count() . " photos";' 2>&1 | tail -2

# 5. R2 bucket listing
echo ""
echo "=== R2 bucket simkk-clinical ==="
AWS_ACCESS_KEY_ID=193baa2eadf09b3b0bbd85b2a53d6aea \
AWS_SECRET_ACCESS_KEY=78505891187b93cf549374e502b1c133510f94565c56e0c96d3723a617ce5d9b \
aws s3 ls s3://simkk-clinical/ --endpoint-url=https://81decd820517795683ad5953ce03f570.r2.cloudflarestorage.com 2>&1
