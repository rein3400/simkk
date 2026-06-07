<?php
chdir('/var/www/sim-kk/apps/api');
require '/var/www/sim-kk/apps/api/vendor/autoload.php';
$app = require '/var/www/sim-kk/apps/api/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$d = \Storage::disk('r2');
$key = 'clinical/RM-2026-0018/36acdcea-4f5c-4cdb-8ffa-943f47b2e152-colored-square.png';
echo "exists=" . (int) $d->exists($key) . PHP_EOL;
echo "size=" . (int) $d->size($key) . PHP_EOL;
echo "visibility=" . $d->getVisibility($key) . PHP_EOL;
echo "exists_old=" . (int) $d->exists('clinical/RM-2026-0018/b6941036-b7e0-4d56-834f-dfc924277604-test-r2.png') . PHP_EOL;
echo "size_old=" . (int) $d->size('clinical/RM-2026-0018/b6941036-b7e0-4d56-834f-dfc924277604-test-r2.png') . PHP_EOL;
