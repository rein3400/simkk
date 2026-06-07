// SIM-KK VPS Deploy Workflow
// Usage: node .workflow/simkk-vps-deploy.wf.mjs --ip=<vps-ip> [--ssh=<key-path>] [--domain=<domain>] [--user=root]
//
// Phase workflow:
export const meta = {
  name: 'simkk-vps-deploy',
  description: 'Deploy SIM-KK stack ke Tencent CVM S5.SMALL2 (Ubuntu 24.04, nginx + PHP-FPM + SQLite + R2 + Telegram)',
  phases: [
    { title: 'Stage 1: Server + stack provisioning' },
    { title: 'Stage 2: R2 buckets + API token' },
    { title: 'Stage 3: apps/api Laravel deploy' },
    { title: 'Stage 4: apps/web Vue build' },
    { title: 'Stage 5: nginx reverse proxy' },
    { title: 'Stage 6: systemd + cron' },
    { title: 'Stage 7: Telegram webhook' },
    { title: 'Stage 8: Uptime Kuma monitoring' },
    { title: 'Stage 9: End-to-end smoke' },
    { title: 'Stage 10: Handoff v1.0' },
  ],
};

// === Args ===
const ip = process.argv.find(a => a.startsWith('--ip='))?.split('=')[1];
const ssh = process.argv.find(a => a.startsWith('--ssh='))?.split('=')[1] || null;
const sshUser = process.argv.find(a => a.startsWith('--user='))?.split('=')[1] || 'root';
const cfToken = process.argv.find(a => a.startsWith('--cf-token='))?.split('=')[1] || null;
const cfAccountId = process.argv.find(a => a.startsWith('--cf-account='))?.split('=')[1] || null;
const tgBotToken = process.argv.find(a => a.startsWith('--tg-token='))?.split('=')[1] || null;
const domain = process.argv.find(a => a.startsWith('--domain='))?.split('=')[1] || 'sim-kk.example.id';

if (!ip) { console.error('Usage: node .workflow/simkk-vps-deploy.wf.mjs --ip=<vps-ip>'); process.exit(1); }

const sshCmd = ssh ? `ssh -i "${ssh}" ${sshUser}@${ip}` : `ssh ${sshUser}@${ip}`;

// === Workflow body (wrapped for ESM) ===
async function run() {

phase('Stage 1: Server + stack provisioning')
const stage1 = await agent(`Provision Tencent CVM S5.SMALL2 (Ubuntu 24.04) untuk SIM-KK.

**IP**: ${ip}
**SSH**: \`${sshCmd}\`

**Steps** (jalankan via SSH):

1. apt update && apt upgrade -y
2. apt install -y software-properties-common
3. add-apt-repository ppa:ondrej/php -y && apt update
4. apt install -y nginx php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-sqlite3 php8.3-gd php8.3-zip php8.3-bcmath php8.3-intl composer nodejs npm unzip git fail2ban ufw sqlite3
5. npm install -g wrangler
6. useradd -m -s /bin/bash deploy
7. mkdir -p /var/www/sim-kk && chown deploy:deploy /var/www/sim-kk
8. **Hardening**:
   - \`sed -i 's/^PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config\`
   - Buat SSH key untuk deploy user, disable root login setelah SSH key tested
   - \`ufw default deny incoming && ufw allow 22,80,443/tcp && ufw enable\`
   - \`systemctl enable fail2ban\`
9. Verify: \`php -v\` (8.3.x), \`nginx -v\`, \`composer --version\`, \`node -v\`, \`wrangler --version\`

**Catatan**: ${domain} adalah domain untuk production. SSL/Cloudflare Origin CA di-setup di Stage 5 setelah nginx running. Kalau lo punya Cloudflare account, tambahkan A record \`api.${domain}\` → \`${ip}\` + \`${domain}\` → \`${ip}\` SEBELUM stage 5 supaya SSL via Cloudflare Origin CA bisa di-apply.

RETURN:
- Confirmation semua tools installed (version list)
- UFW status
- deploy user created (uid)
- File system layout (/var/www/sim-kk exists)`, { label: 'Stage 1: provision server' });

phase('Stage 2: R2 buckets + API token')
const stage2 = await agent(`Setup Cloudflare R2 buckets untuk SIM-KK.

**Credentials**:
- Cloudflare API token: \${cfToken || '<TBD — user provides>'}
- Account ID: \${cfAccountId || '<TBD — user provides>'}

**Steps**:

1. **Create buckets** (via Cloudflare dashboard UI):
   - Bucket 1: \`simkk-clinical\` (foto Before/After klinis)
   - Bucket 2: \`simkk-backups\` (SQLite snapshot + tar backup harian)
   - Region: APAC (Singapore)
   - Default access: Private (signed URL atau custom domain)

2. **Create API token** (dashboard → R2 → Manage API Tokens):
   - Token name: \`simkk-app\`
   - Permissions: Object Read & Write
   - Bucket scope: \`simkk-clinical\` AND \`simkk-backups\`
   - TTL: no expiry
   - Save credentials ke \`/var/www/sim-kk/.r2-credentials\` (chmod 600, owner deploy) untuk Stage 3

3. **Document** semua credentials di output report.

**PENTING**: Kalau CF API token tidak dikasih, jangan assume. Output: "BLOCKED: butuh CLOUDFLARE_API_TOKEN untuk create buckets via API. User perlu create manual via dashboard dan kasih credentials."

**Atau alternatif**: User create bucket manual via dashboard (5 menit), kasih credentials. Lebih reliable.

RETURN:
- Bucket names + region
- Account ID
- API token (masked, last 4 chars only)
- File path untuk credentials
- Catatan blokir (kalau ada)`, { label: 'Stage 2: R2 buckets' });

phase('Stage 3: apps/api Laravel deploy')
const stage3 = await agent(`Deploy apps/api (Laravel 13) ke VPS \${ip}.

**Prerequisite**: Stage 1 (server) + Stage 2 (R2) complete. deploy user exists.

**Steps**:

1. SSH as deploy: \`${sshCmd}\` then \`su - deploy\`
2. \`cd /var/www/sim-kk && git clone https://github.com/<user>/sim-kk.git .\`
3. \`cd apps/api\`
4. \`composer install --no-dev --optimize-autoloader --no-interaction\`
5. \`cp .env.example .env && nano .env\` (atau sed) untuk set:
   - \`APP_KEY=\` (kosong dulu, generate next)
   - \`APP_URL=https://api.${domain}\`
   - \`FRONTEND_URL=https://${domain}\`
   - \`DB_CONNECTION=sqlite\`
   - \`SANCTUM_STATEFUL_DOMAINS=${domain},www.${domain},api.${domain}\`
   - \`SESSION_DOMAIN=.${domain}\`
   - \`STORAGE_DISK=r2\`
   - \`R2_ACCESS_KEY_ID=\` (dari Stage 2)
   - \`R2_SECRET_ACCESS_KEY=\` (dari Stage 2)
   - \`R2_BUCKET=simkk-clinical\`
   - \`R2_ENDPOINT=\` (dari Stage 2)
   - \`R2_REGION=auto\`
   - \`R2_USE_PATH_STYLE_ENDPOINT=true\`
   - \`TELEGRAM_BOT_TOKEN=\` (dari user)
6. \`php artisan key:generate\`
7. \`php artisan migrate --force\`
8. \`php artisan db:seed --class=ProductionBootstrapSeeder --force\`
9. \`php artisan config:cache && php artisan route:cache && php artisan view:cache\`
10. \`php artisan storage:link\`
11. \`mkdir -p storage/framework/cache/data && chown -R deploy:deploy storage bootstrap/cache\`
12. Test: \`php artisan tinker --execute="echo \\\\DB::table('users')->count();"\`

RETURN:
- Migration count applied
- Seeded user list (4 users created)
- Storage symlink created
- Test result
- Final .env (sanitized, no secrets)`, { label: 'Stage 3: apps/api deploy' });

phase('Stage 4: apps/web Vue build')
const stage4 = await agent(`Build apps/web (Vue 3) production bundle di VPS \${ip}.

**Prerequisite**: Stage 3 complete (repo cloned).

**Steps**:

1. SSH as deploy
2. \`cd /var/www/sim-kk/apps/web\`
3. \`npm ci\`
4. \`npm run build\` → hasil di \`dist/\`
5. Verify dist contents: \`ls -la dist/ && du -sh dist/\`
6. Check: \`head -c 500 dist/index.html\` (Vue SPA entry ada)

**Catatan**: Frontend Vue belum full editorial luxury rewrite (cuma AppShell + LoginView yang baru). 5 view lain masih versi lama. Ini OK untuk stage 1 go-live.

RETURN:
- npm ci success / error
- Build size (dist/ total + per-file)
- index.html head 200 chars`, { label: 'Stage 4: build Vue' });

phase('Stage 5: nginx reverse proxy')
const stage5 = await agent(`Configure nginx untuk serve Vue 3 SPA + reverse proxy ke Laravel API.

**Domain**: ${domain}

**Steps**:

1. \`nano /etc/nginx/sites-available/sim-kk\`:
\`\`\`nginx
# API subdomain
server {
    listen 80;
    server_name api.${domain};
    client_max_body_size 20M;

    location / {
        root /var/www/sim-kk/apps/api/public;
        try_files \$uri /index.php?\$query_string;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 60;
    }
}

# Frontend root domain
server {
    listen 80;
    server_name ${domain} www.${domain};
    root /var/www/sim-kk/apps/web/dist;
    index index.html;

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location ~* \\.(js|css|png|jpg|svg|woff2|woff)\$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
\`\`\`

2. \`ln -s /etc/nginx/sites-available/sim-kk /etc/nginx/sites-enabled/\`
3. \`rm /etc/nginx/sites-enabled/default\`
4. \`nginx -t\`
5. \`systemctl reload nginx\`
6. \`ufw allow 'Nginx Full'\`
7. Verify: \`curl -I http://${ip}/api/health\`, \`curl -I http://${ip}/\`

RETURN:
- nginx -t result
- Curl tests output
- File paths`, { label: 'Stage 5: nginx' });

phase('Stage 6: systemd + cron')
const stage6 = await agent(`Setup systemd services + cron untuk SIM-KK di VPS \${ip}.

**Steps**:

1. PHP-FPM sudah enabled by default, verify: \`systemctl status php8.3-fpm\`

2. Laravel scheduler:
\`\`\`bash
# /etc/systemd/system/sim-kk-scheduler.service
[Unit]
Description=SIM-KK Laravel Scheduler
After=network.target php8.3-fpm.service

[Service]
Type=simple
User=deploy
WorkingDirectory=/var/www/sim-kk/apps/api
ExecStart=/usr/bin/php /var/www/sim-kk/apps/api/artisan schedule:work
Restart=always
RestartSec=10
StandardOutput=append:/var/log/sim-kk-scheduler.log
StandardError=append:/var/log/sim-kk-scheduler.log

[Install]
WantedBy=multi-user.target
\`\`\`
\`systemctl daemon-reload && systemctl enable --now sim-kk-scheduler\`

3. Daily backup:
\`\`\`bash
# /etc/cron.daily/sim-kk-backup
#!/bin/bash
set -e
BACKUP_DIR=/var/backups/sim-kk
DATE=\$(date +%Y%m%d)
mkdir -p \$BACKUP_DIR

sqlite3 /var/www/sim-kk/apps/api/database/database.sqlite ".backup '\$BACKUP_DIR/db-\$DATE.sqlite'"

cd /var/www/sim-kk
tar czf \$BACKUP_DIR/app-\$DATE.tar.gz --exclude='vendor' --exclude='node_modules' --exclude='.git' .

/usr/local/bin/aws s3 cp \$BACKUP_DIR/db-\$DATE.sqlite s3://simkk-backups/db/ --endpoint-url=\$R2_ENDPOINT 2>/dev/null || echo "R2 upload skipped"
/usr/local/bin/aws s3 cp \$BACKUP_DIR/app-\$DATE.tar.gz s3://simkk-backups/app/ --endpoint-url=\$R2_ENDPOINT 2>/dev/null || echo "R2 upload skipped"

find \$BACKUP_DIR -mtime +7 -delete
\`\`\`
\`chmod +x /etc/cron.daily/sim-kk-backup\`

4. \`/etc/logrotate.d/sim-kk\`:
\`\`\`
/var/log/sim-kk-scheduler.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
}
\`\`\`

RETURN:
- sim-kk-scheduler.service status
- /etc/cron.daily/sim-kk-backup permissions
- logrotate config
- UFW + fail2ban status`, { label: 'Stage 6: systemd+cron' });

phase('Stage 7: Telegram webhook')
const stage7 = await agent(`Register Telegram webhook untuk SIM-KK di production.

**Bot token**: \${tgBotToken || '<TBD — load from .env>'}

**Steps**:

1. SSH as deploy, \`cd /var/www/sim-kk/apps/api\`
2. Read TELEGRAM_BOT_TOKEN from .env: \`grep TELEGRAM_BOT_TOKEN .env\`
3. Generate secret: \`SECRET=\$(openssl rand -hex 32)\`
4. Append ke .env: \`echo "TELEGRAM_WEBHOOK_SECRET=\$SECRET" >> .env\`
5. Re-cache config: \`php artisan config:clear && php artisan config:cache\`
6. WEBHOOK_URL="https://api.${domain}/api/telegram/webhook"
7. Register webhook:
   \`\`\`
   curl -F "url=\$WEBHOOK_URL" \\
        -F "secret_token=\$SECRET" \\
        https://api.telegram.org/bot\$TELEGRAM_BOT_TOKEN/setWebhook
   \`\`\`
8. Verify: \`curl https://api.telegram.org/bot\$TELEGRAM_BOT_TOKEN/getWebhookInfo\`
9. Test ping: \`curl https://api.\${domain}/api/telegram/webhook\` (should return 503)

**PENTING**: Domain \`api.${domain}\` harus sudah point ke VPS IP via Cloudflare DNS sebelum step ini. SSL via Cloudflare proxy (orange cloud) wajib aktif.

RETURN:
- setWebhook response JSON
- getWebhookInfo output
- Secret token (masked, 4 chars)
- Test ping result`, { label: 'Stage 7: Telegram webhook' });

phase('Stage 8: Uptime Kuma monitoring')
const stage8 = await agent(`Deploy Uptime Kuma untuk monitoring SIM-KK di VPS \${ip}.

**Steps**:

1. SSH as root, install Docker (kalau belum):
   \`curl -fsSL https://get.docker.com | sh && systemctl enable --now docker\`

2. \`mkdir -p /opt/uptime-kuma && cd /opt/uptime-kuma\`

3. \`/etc/docker/daemon.json\`:
   \`\`\`json
   {
     "log-driver": "json-file",
     "log-opts": {"max-size": "10m", "max-file": "3"}
   }
   \`\`\`
   \`systemctl restart docker\`

4. \`docker-compose.yml\`:
   \`\`\`yaml
   version: '3.8'
   services:
     uptime-kuma:
       image: louislam/uptime-kuma:1
       container_name: uptime-kuma
       ports:
         - "127.0.0.1:3001:3001"
       volumes:
         - uptime-kuma:/app/data
         - /var/run/docker.sock:/var/run/docker.sock
       restart: unless-stopped
   volumes:
     uptime-kuma:
   \`\`\`

5. \`docker compose up -d\`

6. Setup behind nginx:
   \`\`\`nginx
   location /monitor/ {
       proxy_pass http://127.0.0.1:3001/;
       proxy_set_header Host \$host;
       proxy_set_header X-Real-IP \$remote_addr;
   }
   \`\`\`

7. **Add monitors** (via API setelah login, atau manual UI):
   - HTTP(s) \`https://api.${domain}/api/health\` interval 60s
   - HTTP(s) \`https://${domain}\` interval 60s
   - HTTP(s) \`https://api.${domain}/api/telegram/webhook\` (GET should return 200)
   - Telegram alert notification (notify on downtime)

8. Access: \`https://${domain}/monitor/\`

RETURN:
- Uptime Kuma container status
- Monitor list dengan status
- Access URL
- Telegram alert configured`, { label: 'Stage 8: monitoring' });

phase('Stage 9: End-to-end smoke test')
const stage9 = await agent(`Run end-to-end smoke test against SIM-KK production.

**Production URL**: https://${domain} (FE) / https://api.${domain} (API)

**Test cases** (jalankan via curl dari lokal lo, dengan creds yang ada di VPS .env):

1. **Health**: \`curl https://api.${domain}/api/health\` → 200 \`{"ok":true}\`
2. **Login all 4 roles**: kasir/terapis/gudang/manajer → 200 + token
3. **Auth boundary**: wrong role → 403, no token → 401
4. **POS transaksi (Kasir)**: pay 1 service → 201 + receipt + commission snapshot
5. **Photo upload (Terapis)**: own pasien → 201, file di R2. Other pasien → 403.
6. **Daily report (Manajer)**: export PDF → 200 + valid PDF + dual TTD
7. **Inventory Movements (Gudang)**: date range → 200 rows. Invalid → 422.
8. **Telegram webhook**: GET → 200, POST tanpa secret → 401, POST dengan secret → handler

RETURN:
- Total tests: pass/fail
- Critical issues (jika ada)
- Commit SHA`, { label: 'Stage 9: E2E smoke' });

phase('Stage 10: Final handoff v1.0')
const stage10 = await agent(`Final handoff package untuk SIM-KK v1.0 production.

**Production URLs**:
- Frontend: https://${domain}
- API: https://api.${domain}
- Monitoring: https://${domain}/monitor/

**Tasks**:

1. Update \`outputs/DELIVERABLE.md\` ke status v1.0 production
2. Update \`outputs/sim-kk-ui-previews/CLIENT-NOTE.md\` dengan production URLs
3. Update \`outputs/changelog.md\` dengan launch date
4. Update \`HALLUCINATION.md\` dengan VPS deployment log
5. Update \`README.md\` dengan production status
6. Final commit
7. Push notification ke user

RETURN:
- Production URLs
- Login credentials
- Monitoring URL
- Commit SHA`, { label: 'Stage 10: handoff v1.0' });

// === FINAL: Aggregate result ===
return {
  ip,
  domain,
  stages: { stage1, stage2, stage3, stage4, stage5, stage6, stage7, stage8, stage9, stage10 },
  status: 'production live at https://' + domain,
  next: 'user access monitoring dashboard + test Telegram flow',
};
} // end run()

// === Invoke ===
run().catch(e => { console.error('Workflow failed:', e); process.exit(1); });
