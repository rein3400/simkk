#!/bin/bash
TOK=$(curl -s http://127.0.0.1/api/login -H "Content-Type: application/json" --data-raw '{"username":"manajer","password":"simkk-2026","level":"Manajer"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
echo "Token len: ${#TOK}"
echo
echo "--- /api/dashboard ---"
curl -s http://127.0.0.1/api/dashboard -H "Authorization: Bearer $TOK" | python3 -m json.tool | head -40
