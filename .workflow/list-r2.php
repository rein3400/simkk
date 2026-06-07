<?php
chdir('/var/www/sim-kk/apps/api');
require '/var/www/sim-kk/apps/api/vendor/autoload.php';
$app = require '/var/www/sim-kk/apps/api/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$disk = \Storage::disk('r2');
try {
    $files = $disk->allFiles('clinical');
    echo "Count: " . count($files) . PHP_EOL;
    foreach (array_slice($files, 0, 10) as $f) {
        echo $f . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "ERR: " . $e->getMessage() . PHP_EOL;
}
