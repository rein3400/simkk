#!/bin/bash
echo "=== All roles login ==="
declare -A CAP=([kasir]=Kasir [terapis]=Terapis [gudang]=Gudang [manajer]=Manajer)
for role in kasir terapis gudang manajer; do
  cap=${CAP[$role]}
  R=$(curl -s -X POST -H "Host: api.sim-kk.example.id" -H "Content-Type: application/json" -d "{\"username\":\"$role\",\"password\":\"simkk-2026\",\"level\":\"$cap\"}" http://43.133.142.74/api/login)
  TOK=$(echo "$R" | grep -o '"token":"[^"]*"' | head -1)
  echo "$role ($cap): $TOK"
done
