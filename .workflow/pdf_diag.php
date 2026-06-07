<?php
require __DIR__ . '/../apps/api/vendor/autoload.php';
$app = require __DIR__ . '/../apps/api/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $svc = new \App\Services\PdfService();
    $ledger = \App\Models\BukuKas::all();
    echo "ledger count: " . $ledger->count() . PHP_EOL;
    $out = $svc->finance($ledger);
    echo "ok len=" . strlen($out) . PHP_EOL;
} catch (\Throwable $e) {
    echo "ERR: " . get_class($e) . PHP_EOL;
    echo "MSG: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
