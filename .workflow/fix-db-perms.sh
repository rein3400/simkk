#!/bin/bash
echo "=== Check DB file perms ==="
sudo -n ls -la /var/www/sim-kk/apps/api/database/database.sqlite
echo ""
echo "=== Check DB dir perms ==="
sudo -n ls -la /var/www/sim-kk/apps/api/database/ | head -10
echo ""
echo "=== Try writing to DB ==="
sudo -n -u www-data bash -c "echo 'test' > /var/www/sim-kk/apps/api/database/database.sqlite.test && rm /var/www/sim-kk/apps/api/database/database.sqlite.test && echo 'www-data CAN write' || echo 'www-data CANNOT write'"
echo ""
echo "=== Fix perms ==="
sudo -n bash -c "chown -R www-data:www-data /var/www/sim-kk/apps/api/database && chmod 775 /var/www/sim-kk/apps/api/database && chmod 664 /var/www/sim-kk/apps/api/database/database.sqlite && ls -la /var/www/sim-kk/apps/api/database/database.sqlite"
echo ""
echo "=== Try writing to DB as www-data ==="
sudo -n -u www-data bash -c "echo 'test' > /var/www/sim-kk/apps/api/database/database.sqlite.test && rm /var/www/sim-kk/apps/api/database/database.sqlite.test && echo 'www-data CAN write' || echo 'www-data CANNOT write'"
echo ""
echo "=== Test login again ==="
sleep 1
curl -s -X POST http://43.133.142.74/api/login -H "Content-Type: application/json" -d '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' -w "\nHTTP=%{http_code}\n"
