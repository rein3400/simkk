<?php
chdir('/var/www/sim-kk/apps/api');
require '/var/www/sim-kk/apps/api/vendor/autoload.php';
$app = require '/var/www/sim-kk/apps/api/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows = \DB::table('foto_klinis')->limit(5)->get(['id', 'label', 'object_ref']);
echo json_encode($rows, JSON_PRETTY_PRINT) . PHP_EOL;
