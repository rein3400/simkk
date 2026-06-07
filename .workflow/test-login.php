<?php
require '/var/www/sim-kk/apps/api/vendor/autoload.php';
$app = require '/var/www/sim-kk/apps/api/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $u = \App\Models\User::where('username', 'kasir')->first();
    if ($u) {
        echo "User found: {$u->username} (id={$u->id})\n";
        echo "Tokens before: " . $u->tokens()->count() . "\n";
        $token = $u->createToken('test-' . time());
        echo "Token created: " . substr($token->plainTextToken, 0, 20) . "...\n";
        echo "Tokens after: " . $u->tokens()->count() . "\n";
        $u->tokens()->delete();
        echo "Cleanup done.\n";
    } else {
        echo "No user kasir found\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
