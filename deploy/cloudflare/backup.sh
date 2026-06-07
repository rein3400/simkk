#!/usr/bin/env bash
# Daily backup SQLite + Laravel storage to Cloudflare R2.
# Install: copy to /home/simkk/sim-kk/deploy/cloudflare/backup.sh
# Cron:    0 2 * * * /home/simkk/sim-kk/deploy/cloudflare/backup.sh >> /var/log/simkk-backup.log 2>&1
set -euo pipefail

# Load R2 credentials from Laravel .env
ENV_FILE="/home/simkk/sim-kk/apps/api/.env"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "FATAL: $ENV_FILE not found" >&2; exit 1
fi

R2_ENDPOINT=$(grep '^R2_ENDPOINT=' "$ENV_FILE" | cut -d= -f2-)
R2_ACCESS_KEY_ID=$(grep '^R2_ACCESS_KEY_ID=' "$ENV_FILE" | cut -d= -f2-)
R2_SECRET_ACCESS_KEY=$(grep '^R2_SECRET_ACCESS_KEY=' "$ENV_FILE" | cut -d= -f2-)
TS=$(date +%Y%m%d-%H%M%S)
DB="/home/simkk/sim-kk/apps/api/database/database.sqlite"
BACKUP_BUCKET="simkk-backups"

export AWS_ACCESS_KEY_ID="$R2_ACCESS_KEY_ID"
export AWS_SECRET_ACCESS_KEY="$R2_SECRET_ACCESS_KEY"

echo "[$TS] Starting backup..."

# 1. SQLite (use sqlite3 .backup for safe hot copy)
TMP_DB="/tmp/simkk-$TS.sqlite"
sqlite3 "$DB" ".backup '$TMP_DB'"
echo "[$TS] DB copy: $TMP_DB ($(stat -c %s "$TMP_DB") bytes)"

# 2. Upload
aws s3 cp "$TMP_DB" "s3://$BACKUP_BUCKET/db-$TS.sqlite" \
  --endpoint-url "$R2_ENDPOINT" --only-show-errors
rm -f "$TMP_DB"
echo "[$TS] DB uploaded to s3://$BACKUP_BUCKET/db-$TS.sqlite"

# 3. Retention: keep last 30 days
OLD=$(aws s3 ls "s3://$BACKUP_BUCKET/" --endpoint-url "$R2_ENDPOINT" \
  | awk '{print $4}' | grep -E '^db-[0-9]{8}-[0-9]{6}\.sqlite$' | sort | head -n -30 || true)
if [[ -n "$OLD" ]]; then
  for f in $OLD; do
    aws s3 rm "s3://$BACKUP_BUCKET/$f" --endpoint-url "$R2_ENDPOINT" --only-show-errors
    echo "[$TS] Pruned old backup: $f"
  done
fi

echo "[$TS] Backup complete."
