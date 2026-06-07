#!/bin/bash
TOK=$(curl -s http://127.0.0.1/api/login -H "Content-Type: application/json" --data-raw '{"username":"terapis","password":"simkk-2026","level":"Terapis"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
echo "Token len: ${#TOK}"
echo
echo "--- Photo 7 (real R2) ---"
curl -sI http://127.0.0.1/api/photos/7/raw -H "Authorization: Bearer $TOK" | head -5
echo
echo "--- Photo 7 body head ---"
curl -s http://127.0.0.1/api/photos/7/raw -H "Authorization: Bearer $TOK" -o /tmp/p7.bin
file /tmp/p7.bin
ls -la /tmp/p7.bin
echo
echo "--- Photo 1 (legacy local://) ---"
curl -sI http://127.0.0.1/api/photos/1/raw -H "Authorization: Bearer $TOK" | head -5
echo
echo "--- Photo 5 (UUID, no R2 file) ---"
curl -sI http://127.0.0.1/api/photos/5/raw -H "Authorization: Bearer $TOK" | head -5
echo
echo "--- Photo 1 body head ---"
curl -s http://127.0.0.1/api/photos/1/raw -H "Authorization: Bearer $TOK" -o /tmp/p1.bin
file /tmp/p1.bin
ls -la /tmp/p1.bin
