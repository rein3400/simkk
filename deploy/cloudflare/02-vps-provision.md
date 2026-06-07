# 02 — DigitalOcean VPS Provision (10 min)

1GB RAM, Singapore region, Ubuntu 24.04 LTS. ~$4-6/bulan.

## Steps (Web UI)

1. Login ke https://cloud.digitalocean.com
2. **Create → Droplets**
3. Configure:
   - **Region**: Singapore (SGP1) — latency ~30ms ke Samarinda
   - **Image**: Ubuntu 24.04 LTS x64
   - **Size**: Basic → Regular → **$4/bulan (1GB RAM, 25GB SSD, 1TB transfer)**
   - **Authentication**: SSH key (upload public key `~/.ssh/id_ed25519.pub`) — JANGAN password
   - **Hostname**: `sim-kk-api`
4. Klik **Create Droplet**
5. Tunggu ~1 menit sampai IP muncul (mis. `128.199.45.67`)

## Verifikasi SSH

```bash
ssh root@128.199.45.67 "uname -a; uptime"
# Expected: Linux sim-kk-api 6.x.x ... x86_64
```

## Lock down server (WAJIB, 10 min)

Login sebagai root, lalu:

```bash
# 1. Update
apt update && apt upgrade -y

# 2. Create non-root user
adduser simkk --disabled-password --gecos ""
mkdir -p /home/simkk/.ssh
cp ~/.ssh/authorized_keys /home/simkk/.ssh/
chown -R simkk:simkk /home/simkk/.ssh
chmod 700 /home/simkk/.ssh
chmod 600 /home/simkk/.ssh/authorized_keys
echo "simkk ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/simkk

# 3. Firewall
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
# Port 80/443 TIDAK dibuka langsung — semua lewat Cloudflare Tunnel
ufw enable
ufw status

# 4. Disable root login + password auth
sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart sshd

# 5. Fail2ban
apt install -y fail2ban
systemctl enable fail2ban
systemctl start fail2ban
```

## Install runtime stack (5 min)

```bash
# PHP 8.3 + extensions
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml \
               php8.3-curl php8.3-zip php8.3-sqlite3 php8.3-bcmath php8.3-intl

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
composer --version

# Nginx
apt install -y nginx
systemctl enable nginx
systemctl start nginx

# SQLite (default DB, sudah include di php8.3-sqlite3)
apt install -y sqlite3
```

## Verifikasi

```bash
ssh simkk@128.199.45.67 "php --version; composer --version; nginx -v 2>&1 | head -1; sqlite3 --version"
# Expected: PHP 8.3.x, Composer 2.x.x, nginx 1.24.x, sqlite 3.x
```

## Catat

```
VPS_IP=128.199.45.67
VPS_USER=simkk
SSH_KEY_PATH=~/.ssh/id_ed25519
```

Lanjut ke step 03-deploy-api.md.
