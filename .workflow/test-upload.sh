#!/bin/bash
# Generate small colored PNG and upload via /api/patients/1/photos
TOK=$(curl -s http://127.0.0.1/api/login -H "Content-Type: application/json" --data-raw '{"username":"terapis","password":"simkk-2026","level":"Terapis"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")

# Create a 100x100 colored PNG (red square)
python3 -c "
import struct, zlib, base64
w=h=120
color = (200, 80, 60)
raw = b''
for y in range(h):
    raw += b'\x00'
    for x in range(w):
        r,g,b = color
        raw += bytes([r,g,b])
comp = zlib.compress(raw)
def chunk(typ, data):
    crc = zlib.crc32(typ + data) & 0xffffffff
    return struct.pack('>I', len(data)) + typ + data + struct.pack('>I', crc)
png = b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', w, h, 8, 2, 0, 0, 0)) + chunk(b'IDAT', comp) + chunk(b'IEND', b'')
print(base64.b64encode(png).decode())
" > /tmp/b64.txt
B64=$(cat /tmp/b64.txt)

# Upload
RESP=$(curl -s http://127.0.0.1/api/patients/1/photos \
  -H "Authorization: Bearer $TOK" \
  -H "Content-Type: application/json" \
  --data-raw "{\"label\":\"Before\",\"filename\":\"colored-square.png\",\"content\":\"data:image/png;base64,$B64\",\"mimeType\":\"image/png\"}")
echo "UPLOAD RESP:"
echo "$RESP" | head -c 600
echo

# Check it has url now
echo
URL=$(echo "$RESP" | python3 -c "import sys,json; print(json.load(sys.stdin).get('url',''))")
echo "URL: $URL"

if [ -n "$URL" ]; then
  echo
  echo "Testing fetch URL..."
  curl -sI "$URL" 2>&1 | head -8
fi
