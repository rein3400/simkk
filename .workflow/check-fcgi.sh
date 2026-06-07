#!/bin/bash
echo "=== Add debug log to specific request ==="
# Reset access log
sudo -n truncate -s 0 /var/log/nginx/access.log
sudo -n truncate -s 0 /var/log/nginx/error.log
sleep 1

echo "=== curl with -v (verbose) ==="
curl -v -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' 2>&1 | head -30
echo ""
echo "=== access log ==="
sudo -n cat /var/log/nginx/access.log
echo ""
echo "=== error log ==="
sudo -n cat /var/log/nginx/error.log

echo "=== Test PHP-FPM direct ==="
# Test if PHP-FPM is even responsive
echo "<?php echo 'pong'; ?>" | sudo -n tee /var/www/sim-kk/apps/api/public/ping.php > /dev/null
curl -s -m 5 http://43.133.142.74/api/ping.php -w "\nHTTP=%{http_code}\n"
sudo -n rm /var/www/sim-kk/apps/api/public/ping.php
