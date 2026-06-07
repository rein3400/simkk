<?php
chdir('/var/www/sim-kk/apps/api');
require '/var/www/sim-kk/apps/api/vendor/autoload.php';
$app = require '/var/www/sim-kk/apps/api/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$d = \Storage::disk('r2');
echo $d->temporaryUrl('clinical/RM-2026-0018/36acdcea-4f5c-4cdb-8ffa-943f47b2e152-colored-square.png', now()->addMinutes(10));
