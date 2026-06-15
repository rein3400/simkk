# Deploy SIM-KK to VPS via password-based SSH
param(
    [string]$VpsHost = "43.133.142.74",
    [string]$VpsUser = "ubuntu"
)

$ErrorActionPreference = "Stop"
$archive = "D:\users\stefa\project\sim-kk\.workflow\simkk-deploy.tar.gz"

Write-Host "=== SIM-KK VPS Deploy ==="
Write-Host "Server: ${VpsUser}@${VpsHost}"
Write-Host ""

# 1. Build Vue SPA
Write-Host "[1/5] Building Vue SPA..."
Push-Location D:\users\stefa\project\sim-kk\apps\web
npm run build 2>&1 | Select-Object -Last 3
Pop-Location

# 2. Create deployment archive (API code + web dist)
Write-Host "[2/5] Creating deployment archive..."
Push-Location D:\users\stefa\project\sim-kk
# Archive Laravel app code and Vue dist
& tar -czf $archive `
    -C apps/api `
    app/ config/ database/ routes/ resources/ `
    composer.json composer.lock artisan `
    -C ../web `
    dist/
Pop-Location
$size = (Get-Item $archive).Length
Write-Host "   Archive: $([math]::Round($size/1024)) KB"

# 3. Upload via SCP (will prompt for password once)
Write-Host "[3/5] Uploading to VPS..."
& scp -o StrictHostKeyChecking=no $archive "${VpsUser}@${VpsHost}:/tmp/simkk-deploy.tar.gz"
if ($LASTEXITCODE -ne 0) {
    Write-Host "SCP upload failed. Make sure password is correct."
    exit 1
}
Write-Host "   Upload OK"

# 4. Remote deploy commands
Write-Host "[4/5] Deploying on VPS..."
$deployScript = @'
set -e
cd /tmp
tar -xzf simkk-deploy.tar.gz -C /tmp/simkk-stage/
rm -rf /tmp/simkk-stage
mkdir -p /tmp/simkk-stage
tar -xzf simkk-deploy.tar.gz -C /tmp/simkk-stage/

# Copy API code
sudo cp -r /tmp/simkk-stage/app/* /var/www/sim-kk/apps/api/app/
sudo cp -r /tmp/simkk-stage/config/* /var/www/sim-kk/apps/api/config/
sudo cp -r /tmp/simkk-stage/database/* /var/www/sim-kk/apps/api/database/
sudo cp -r /tmp/simkk-stage/routes/* /var/www/sim-kk/apps/api/routes/
sudo cp -r /tmp/simkk-stage/resources/* /var/www/sim-kk/apps/api/resources/ 2>/dev/null || true
sudo cp /tmp/simkk-stage/composer.json /var/www/sim-kk/apps/api/ 2>/dev/null || true
sudo cp /tmp/simkk-stage/composer.lock /var/www/sim-kk/apps/api/ 2>/dev/null || true
sudo cp /tmp/simkk-stage/artisan /var/www/sim-kk/apps/api/ 2>/dev/null || true

# Copy Vue dist
sudo rm -rf /var/www/sim-kk/apps/web/dist
sudo cp -r /tmp/simkk-stage/dist /var/www/sim-kk/apps/web/dist

# Set permissions
sudo chown -R www-data:www-data /var/www/sim-kk/apps
sudo chmod -R 755 /var/www/sim-kk/apps

# Run migrations
cd /var/www/sim-kk/apps/api
sudo -u www-data php artisan migrate --force 2>&1

# Clear cache
sudo -u www-data php artisan config:clear 2>&1
sudo -u www-data php artisan route:clear 2>&1
sudo -u www-data php artisan cache:clear 2>&1

# Reload nginx
sudo systemctl reload nginx

# Health check
sleep 1
curl -s http://localhost/api/health
echo ""
echo "Deploy complete."
'@

$deployScript | ssh -o StrictHostKeyChecking=no "${VpsUser}@${VpsHost}" "bash -s" 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "Remote deploy failed."
    exit 1
}

# 5. Verify
Write-Host ""
Write-Host "[5/5] Verifying deployment..."
$health = curl -s http://43.133.142.74/api/health 2>&1
$login = curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"manajer","password":"simkk-2026","level":"Manajer"}' 2>&1

Write-Host "   /api/health: $health"
Write-Host "   /api/login (Manajer): $($login -like '*token*')"
Write-Host ""
Write-Host "=== DEPLOY COMPLETE ==="
