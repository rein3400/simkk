#!/bin/bash
# Stage 2: R2 buckets + scoped API token
set -e
export CLOUDFLARE_API_TOKEN="81decd820517795683ad5953ce03f570"
export CLOUDFLARE_ACCOUNT_ID="81decd820517795683ad5953ce03f570"  # Will fix if different from API token

echo "=== CLOUDFLARE ACCOUNT LOOKUP ==="
ACCT_INFO=$(curl -s -X GET "https://api.cloudflare.com/client/v4/accounts?per_page=1" \
  -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" \
  -H "Content-Type: application/json")
echo "$ACCT_INFO" | head -c 500
echo ""
ACCT_ID=$(echo "$ACCT_INFO" | php -r 'echo json_decode(stream_get_contents(STDIN), true)["result"][0]["id"] ?? "";')
echo "Detected account ID: $ACCT_ID"

echo ""
echo "=== CREATE R2 BUCKETS ==="
for BUCKET in simkk-clinical simkk-backups; do
  echo "--- $BUCKET ---"
  curl -s -X POST "https://api.cloudflare.com/client/v4/accounts/${ACCT_ID}/r2/buckets" \
    -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"name\":\"$BUCKET\",\"locationHint\":\"apac\"}" | head -c 400
  echo ""
done

echo ""
echo "=== LIST R2 BUCKETS ==="
curl -s "https://api.cloudflare.com/client/v4/accounts/${ACCT_ID}/r2/buckets?per_page=20" \
  -H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" | php -r '
$d = json_decode(stream_get_contents(STDIN), true);
if (isset($d["result"])) {
    foreach ($d["result"]["buckets"] ?? $d["result"] as $b) {
        echo "  - " . $b["name"] . " (" . $b["location"] . ")\n";
    }
} else {
    echo json_encode($d, JSON_PRETTY_PRINT) . "\n";
}'

echo ""
echo "=== R2 API TOKEN (manual: dashboard → R2 → Manage API Tokens) ==="
echo "  1. Open https://dash.cloudflare.com/?to=/:account/r2/api-tokens"
echo "  2. Create token 'simkk-app' with Object Read & Write scope to simkk-clinical + simkk-backups"
echo "  3. Copy Access Key ID + Secret Access Key"
echo "  4. Put into apps/api/.env on VPS"
