#!/bin/bash
echo "=== Apply final nginx (v14 — IP=Vue, subdomain=API) ==="
sudo -n bash -c "cp /tmp/sim-kk-v14.conf /etc/nginx/sites-available/sim-kk && nginx -t 2>&1 && systemctl reload nginx"
sleep 1
echo ""
echo "=== Test 1: SPA on IP ==="
curl -s -o /dev/null -w "GET / HTTP=%{http_code} size=%{size_download}\n" http://43.133.142.74/
echo ""
echo "=== Test 2: API health on subdomain ==="
curl -s -H "Host: api.sim-kk.example.id" -m 5 http://43.133.142.74/api/health
echo ""
echo ""
echo "=== Test 3: Login on subdomain ==="
curl -s -X POST -H "Host: api.sim-kk.example.id" -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' http://43.133.142.74/api/login -w "\nHTTP=%{http_code}\n" | head -c 300
echo ""
echo "=== Test 4: CORS preflight (OPTIONS) from IP ==="
curl -s -X OPTIONS http://43.133.142.74/api/login -H "Origin: http://43.133.142.74" -H "Access-Control-Request-Method: POST" -H "Host: api.sim-kk.example.id" -i 2>&1 | head -10
echo ""
echo "=== Test 5: All roles login ==="
for role in kasir terapis gudang manajer; do
  R=$(curl -s -X POST -H "Host: api.sim-kk.example.id" -H "Content-Type: application/json" -d "{\"username\":\"$role\",\"password\":\"simkk-2026\",\"level\":\"$(echo ${role:0:1} | tr a-z A-Z)${role:1}\"}" http://43.133.142.74/api/login 2>&1)
  T=$(echo "$R" | python -c "import sys,json; print(len(json.load(sys.stdin).get('token','')))" 2>/dev/null)
  echo "$role: token=${T:-FAIL}"
done
