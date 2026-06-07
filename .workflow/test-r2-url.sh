#!/bin/bash
TOK=$(curl -s http://127.0.0.1/api/login -H "Content-Type: application/json" --data-raw '{"username":"terapis","password":"simkk-2026","level":"Terapis"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")
echo "Token len: ${#TOK}"
curl -s http://127.0.0.1/api/bootstrap -H "Authorization: Bearer $TOK" | python3 -c "
import sys, json
d = json.load(sys.stdin)
photos = d['patients'][0]['photos']
for p in photos:
    url = p.get('url', '')
    print(f\"  id={p['id']} ref={p['objectRef'][:50]}... url={'YES' if url else 'NULL'}\")
    if url:
        # Test fetch
        import urllib.request
        try:
            req = urllib.request.Request(url, method='GET')
            with urllib.request.urlopen(req, timeout=10) as r:
                print(f'    FETCH OK status={r.status} ctype={r.headers.get(\"Content-Type\")} len={r.headers.get(\"Content-Length\")}')
        except Exception as e:
            print(f'    FETCH ERR: {e}')
"
