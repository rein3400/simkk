#!/bin/bash
echo "=== FINAL E2E — browser-like (no Host override) ==="
echo ""
echo "[1] Vue SPA"
curl -s -o /dev/null -w "GET / HTTP=%{http_code} size=%{size_download} type=%{content_type}\n" http://43.133.142.74/
echo ""
echo "[2] Vue assets (check new bundle hash)"
curl -s -o /dev/null -w "GET /assets/index-DC3VGHZx.js HTTP=%{http_code} type=%{content_type}\n" http://43.133.142.74/assets/index-DC3VGHZx.js
echo ""
echo "[3] API health"
curl -s -m 5 http://43.133.142.74/api/health
echo ""
echo ""
echo "[4] Login all roles (no Host override)"
for role in kasir terapis gudang manajer; do
  cap=$(echo "$role" | sed 's/^./\U&/')
  R=$(curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d "{\"username\":\"$role\",\"password\":\"simkk-2026\",\"level\":\"$cap\"}")
  T=$(echo "$R" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
  if [ -n "$T" ]; then
    echo "  ✓ $role → ${#T} chars token"
  else
    echo "  ✗ $role → $R"
  fi
done
echo ""
echo "[5] Wrong password"
R=$(curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"WRONG","level":"Kasir"}')
echo "  $R"
echo ""
echo "[6] Bootstrap (manajer)"
MTOK=$(curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"manajer","password":"simkk-2026","level":"Manajer"}' | grep -o '"token":"[^"]*"' | cut -d'"' -f4)
R=$(curl -s -H "Authorization: Bearer $MTOK" http://43.133.142.74/api/bootstrap)
echo "  Bootstrap keys: $(echo "$R" | grep -oE '"(users|patients|services|therapists|inventory|reports)":' | head -10 | tr '\n' ' ')"
echo ""
echo "[7] No token"
R=$(curl -s http://43.133.142.74/api/bootstrap)
echo "  $R"
