#!/bin/bash
# Login + upload colored PNG to pasien 1
TOK=$(curl -s http://127.0.0.1/api/login -H "Content-Type: application/json" --data-raw '{"username":"terapis","password":"simkk-2026","level":"Terapis"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
echo "Token len: ${#TOK}"

# Create a bigger colorful PNG (200x200 with 4 quadrants)
python3 << 'PY' > /tmp/b64.txt
import struct, zlib, base64
w=h=200
raw = b''
for y in range(h):
    raw += b'\x00'
    for x in range(w):
        if x < w//2 and y < h//2: raw += bytes([220, 80, 60])    # TL red
        elif x >= w//2 and y < h//2: raw += bytes([60, 180, 100]) # TR green
        elif x < w//2 and y >= h//2: raw += bytes([80, 100, 220]) # BL blue
        else: raw += bytes([230, 200, 80])  # BR gold
comp = zlib.compress(raw)
def chunk(typ, data):
    crc = zlib.crc32(typ + data) & 0xffffffff
    return struct.pack('>I', len(data)) + typ + data + struct.pack('>I', crc)
png = b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', w, h, 8, 2, 0, 0, 0)) + chunk(b'IDAT', comp) + chunk(b'IEND', b'')
print(base64.b64encode(png).decode())
PY

B64=$(cat /tmp/b64.txt)
echo "PNG base64 size: ${#B64}"

RESP=$(curl -s http://127.0.0.1/api/patients/1/photos \
  -H "Authorization: Bearer $TOK" \
  -H "Content-Type: application/json" \
  --data-raw "{\"label\":\"After\",\"filename\":\"quadrants.png\",\"content\":\"data:image/png;base64,$B64\",\"mimeType\":\"image/png\"}")
echo "UPLOAD: $RESP" | head -c 400

# Check R2 has the new file
echo
echo "--- R2 file count ---"
cd /var/www/sim-kk/apps/api
php artisan tinker --execute="echo count(\\Storage::disk('r2')->allFiles('clinical'));" 2>/dev/null
echo "files in bucket"

# Test the new photo URL
PHOTO_ID=$(echo "$RESP" | python3 -c "import sys,json; print(json.load(sys.stdin).get('id',''))")
echo
echo "--- Photo $PHOTO_ID through proxy ---"
curl -sI http://127.0.0.1/api/photos/$PHOTO_ID/raw -H "Authorization: Bearer $TOK" | head -5
