#!/bin/bash
# VPS auto-rebuild script — pulls latest, rebuilds SPA, reloads nginx
# Usage: bash .workflow/vps-rebuild.sh
set -e
cd /var/www/sim-kk
git pull origin main
cd apps/web
npm ci --prefer-offline --no-audit --progress=false
npm run build
cd ..
sudo systemctl reload nginx
echo "=== REBUILD DONE ==="
git log --oneline -1
