#!/bin/bash
TOK=$(curl -s http://127.0.0.1/api/login -H "Content-Type: application/json" --data-raw '{"username":"manajer","password":"simkk-2026","level":"Manajer"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
echo "Token len: ${#TOK}"
curl -s http://127.0.0.1/api/bootstrap -H "Authorization: Bearer $TOK" | python3 -c "
import sys, json
d = json.load(sys.stdin)
print('Top-level keys:', list(d.keys()))
for k, v in d.items():
    if isinstance(v, list):
        print(f'  {k}: {len(v)} items')
        if v and isinstance(v[0], dict):
            print(f'    fields: {list(v[0].keys())}')
    else:
        print(f'  {k}: {type(v).__name__} = {v}')
"
