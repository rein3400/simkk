#!/bin/bash
LOGIN=$(curl -sS -X POST -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","role":"Kasir"}' "http://127.0.0.1:8000/api/login")
TK=$(echo "$LOGIN" | grep -oP '"token":"[^"]+' | head -1 | cut -d'"' -f4)
echo "Token: $TK"
echo "$TK" > /tmp/kasir_tk
