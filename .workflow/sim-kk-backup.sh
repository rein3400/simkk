#!/bin/bash
# SIM-KK daily backup script
# Cron: /etc/cron.daily/sim-kk-backup
# Backups SQLite database + source to /var/backups/sim-kk/

set -e
BACKUP_DIR=/var/backups/sim-kk
DATE=$(date +%Y%m%d)
mkdir -p $BACKUP_DIR

# 1. SQLite hot backup (no lock)
sqlite3 /var/www/sim-kk/apps/api/database/database.sqlite ".backup '$BACKUP_DIR/db-$DATE.sqlite'"

# 2. Source code snapshot
cd /var/www/sim-kk
tar czf $BACKUP_DIR/app-$DATE.tar.gz \
  --exclude='apps/api/vendor' \
  --exclude='apps/api/node_modules' \
  --exclude='apps/web/node_modules' \
  --exclude='.git' \
  apps/api apps/web docs

# 3. Upload to R2 (skip silently if awscli not available)
which aws >/dev/null 2>&1 && {
  AWS_ACCESS_KEY_ID=$R2_ACCESS_KEY_ID \
  AWS_SECRET_ACCESS_KEY=$R2_SECRET_ACCESS_KEY \
  aws s3 cp $BACKUP_DIR/db-$DATE.sqlite s3://simkk-backups/db/ --endpoint-url=$R2_ENDPOINT 2>/dev/null || true
  AWS_ACCESS_KEY_ID=$R2_ACCESS_KEY_ID \
  AWS_SECRET_ACCESS_KEY=$R2_SECRET_ACCESS_KEY \
  aws s3 cp $BACKUP_DIR/app-$DATE.tar.gz s3://simkk-backups/app/ --endpoint-url=$R2_ENDPOINT 2>/dev/null || true
} || echo "awscli not installed, skipping R2 upload"

# 4. Cleanup local > 7 days
find $BACKUP_DIR -mtime +7 -delete 2>/dev/null || true
