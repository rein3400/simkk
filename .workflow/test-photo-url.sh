#!/bin/bash
RESP=$(curl -s -X POST http://127.0.0.1/api/login -H "Content-Type: application/json" --data-raw '{"username":"terapis","password":"simkk-2026","level":"Terapis"}')
echo "LOGIN: $RESP"
TOK=$(echo "$RESP" | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
echo "TOK_LEN: ${#TOK}"
echo "BOOTSTRAP PHOTOS:"
curl -s http://127.0.0.1/api/bootstrap -H "Authorization: Bearer $TOK" | python3 -c "
import sys, json
d = json.load(sys.stdin)
photos = d['patients'][0]['photos'][:3]
print(json.dumps(photos, indent=2))
"
