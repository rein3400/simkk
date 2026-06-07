#!/bin/bash
echo "=== Apply v11 ==="
sudo -n bash -c "cp /tmp/sim-kk-v11.conf /etc/nginx/sites-available/sim-kk && nginx -t 2>&1 && systemctl reload nginx"
sleep 1
echo ""
echo "=== Test direct PHP ==="
echo "<?php echo 'OK:'.(\$_SERVER['REQUEST_METHOD']??'').':'.\$_SERVER['REQUEST_URI']; ?>" | sudo -n tee /var/www/sim-kk/apps/api/public/dbg.php > /dev/null
sudo -n chown www-data:www-data /var/www/sim-kk/apps/api/public/dbg.php
curl -s -X POST http://43.133.142.74/api/dbg.php -d "x=1"
echo ""
echo "=== Test login ==="
curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' -w "\nHTTP=%{http_code}\n" | head -c 400
echo ""
echo "=== Test SPA ==="
curl -s -o /dev/null -w "GET / HTTP=%{http_code} size=%{size_download}\n" http://43.133.142.74/
sudo -n rm /var/www/sim-kk/apps/api/public/dbg.php
echo ""
echo "=== error log ==="
sudo -n tail -3 /var/log/nginx/error.log
