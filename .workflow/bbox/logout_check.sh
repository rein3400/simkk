#!/bin/bash
# Fresh login, then logout, then check token
LOGIN=$(curl -sS -X POST -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","role":"Kasir"}' "http://127.0.0.1:8000/api/login")
echo "Login: $LOGIN"
TK=$(echo "$LOGIN" | grep -oP '"token":"[^"]+' | head -1 | cut -d'"' -f4)
echo "Token: $TK"

# BS with token
STATUS=$(curl -sS -o /tmp/bs1.json -w "%{http_code}" -H "Authorization: Bearer $TK" "http://127.0.0.1:8000/api/bootstrap")
echo "BS1: $STATUS"

# Logout
STATUS=$(curl -sS -o /tmp/lo.json -w "%{http_code}" -X POST -H "Authorization: Bearer $TK" -H "Content-Type: application/json" -d '{}' "http://127.0.0.1:8000/api/logout")
echo "Logout: $STATUS"
echo "Logout body: $(cat /tmp/lo.json)"

# BS after logout
STATUS=$(curl -sS -o /tmp/bs2.json -w "%{http_code}" -H "Authorization: Bearer $TK" "http://127.0.0.1:8000/api/bootstrap")
echo "BS2: $STATUS"
echo "BS2 body: $(head -c 200 /tmp/bs2.json)"

# Re-login same user, get new token
LOGIN2=$(curl -sS -X POST -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","role":"Kasir"}' "http://127.0.0.1:8000/api/login")
echo "Re-login: $LOGIN2"
TK2=$(echo "$LOGIN2" | grep -oP '"token":"[^"]+' | head -1 | cut -d'"' -f4)
echo "TK2: $TK2"
STATUS=$(curl -sS -o /tmp/bs3.json -w "%{http_code}" -H "Authorization: Bearer $TK2" "http://127.0.0.1:8000/api/bootstrap")
echo "BS3: $STATUS"

# Try logout with no body
STATUS=$(curl -sS -o /tmp/lo2.json -w "%{http_code}" -X POST -H "Authorization: Bearer $TK2" "http://127.0.0.1:8000/api/logout")
echo "Logout no body: $STATUS body=$(cat /tmp/lo2.json)"

# Try logout with bad method
STATUS=$(curl -sS -o /tmp/lo3.json -w "%{http_code}" -X GET -H "Authorization: Bearer $TK2" "http://127.0.0.1:8000/api/logout")
echo "Logout GET: $STATUS"
