#!/bin/bash
# SIM-KK Web Console Deploy Script
# Paste ini ke SumoPod VPS Web Console
# Script ini write semua changed files + migrate + clear cache + reload nginx
set -e
echo "=== SIM-KK DEPLOY START ==="
cd /var/www/sim-kk/apps/api

# Backup .env
cp .env .env.backup.$(date +%s) 2>/dev/null || true

echo "=== 1. Write DeployController.php ==="
cat > app/Http/Controllers/Api/DeployController.php << 'PHP_EOF'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class DeployController extends Controller
{
    private const DEPLOY_DIR = '/var/www/sim-kk';

    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('sim-kk.deploy_secret', env('DEPLOY_SECRET'));
        $provided = $request->header('X-Deploy-Secret');
        if (empty($secret) || $provided !== $secret) {
            return response()->json(['message' => 'Deploy secret invalid.'], 403);
        }

        $b64 = $request->input('archive');
        if (empty($b64)) {
            return response()->json(['message' => 'No archive provided.'], 422);
        }

        $bytes = base64_decode($b64, true);
        if ($bytes === false) {
            return response()->json(['message' => 'Archive is not valid base64.'], 422);
        }

        $tmpPath = '/tmp/simkk-deploy-' . time() . '.tar.gz';
        file_put_contents($tmpPath, $bytes);

        $stageDir = '/tmp/simkk-stage-' . time();
        @mkdir($stageDir, 0755, true);

        $log = [];
        $log[] = "Stage: extracting {$tmpPath} to {$stageDir}";

        $extract = Process::fromShellCommandline("tar -xzf {$tmpPath} -C {$stageDir}");
        $extract->setTimeout(120);
        $extract->run();
        if (!$extract->isSuccessful()) {
            return response()->json(['message' => 'Extract failed: ' . $extract->getErrorOutput()], 500);
        }

        $log[] = "Stage: copying files to " . self::DEPLOY_DIR;

        $copyCmds = [
            "cp -r {$stageDir}/app/* " . self::DEPLOY_DIR . "/apps/api/app/",
            "cp -r {$stageDir}/config/* " . self::DEPLOY_DIR . "/apps/api/config/",
            "cp -r {$stageDir}/database/* " . self::DEPLOY_DIR . "/apps/api/database/",
            "cp -r {$stageDir}/routes/* " . self::DEPLOY_DIR . "/apps/api/routes/",
            "cp -r {$stageDir}/resources/* " . self::DEPLOY_DIR . "/apps/api/resources/ 2>/dev/null || true",
            "rm -rf " . self::DEPLOY_DIR . "/apps/web/dist",
            "cp -r {$stageDir}/dist " . self::DEPLOY_DIR . "/apps/web/dist",
            "chown -R www-data:www-data " . self::DEPLOY_DIR . "/apps",
            "chmod -R 755 " . self::DEPLOY_DIR . "/apps",
        ];
        foreach ($copyCmds as $cmd) {
            $p = Process::fromShellCommandline($cmd);
            $p->setTimeout(60);
            $p->run();
            if (!$p->isSuccessful()) {
                $log[] = "WARN: $cmd — " . $p->getErrorOutput();
            }
        }

        $log[] = "Stage: running migrations";
        $migrate = Process::fromShellCommandline(
            "cd " . self::DEPLOY_DIR . "/apps/api && php artisan migrate --force --no-interaction"
        );
        $migrate->setTimeout(120);
        $migrate->run();
        $log[] = "Migrate: " . ($migrate->isSuccessful() ? "OK" : $migrate->getErrorOutput());

        $cacheClears = ['config:clear', 'route:clear', 'cache:clear', 'view:clear'];
        foreach ($cacheClears as $cmd) {
            $p = Process::fromShellCommandline(
                "cd " . self::DEPLOY_DIR . "/apps/api && php artisan {$cmd}"
            );
            $p->setTimeout(30);
            $p->run();
        }

        $reload = Process::fromShellCommandline("systemctl reload nginx");
        $reload->setTimeout(10);
        $reload->run();
        $log[] = "Nginx reload: " . ($reload->isSuccessful() ? "OK" : $reload->getErrorOutput());

        $health = Process::fromShellCommandline("curl -s http://localhost/api/health");
        $health->setTimeout(10);
        $health->run();
        $log[] = "Health: " . $health->getOutput();

        @unlink($tmpPath);
        $cleanup = Process::fromShellCommandline("rm -rf {$stageDir}");
        $cleanup->run();

        return response()->json(['deployed' => true, 'log' => $log]);
    }
}
PHP_EOF
echo "DeployController OK"

echo "=== 2. Add deploy_secret to .env ==="
# Generate random secret
DEPLOY_SECRET=$(openssl rand -hex 16)
if ! grep -q "^DEPLOY_SECRET=" .env; then
    echo "DEPLOY_SECRET=$DEPLOY_SECRET" >> .env
    echo "DEPLOY_SECRET generated: $DEPLOY_SECRET"
else
    DEPLOY_SECRET=$(grep "^DEPLOY_SECRET=" .env | cut -d= -f2)
    echo "DEPLOY_SECRET already exists: $DEPLOY_SECRET"
fi

echo "=== 3. Update routes/api.php ==="
# Add DeployController route
if ! grep -q "DeployController" routes/api.php; then
    # Find line with "Route::post('/backup/trigger'" and add deploy route before it
    sed -i '/Route::post(.\/backup\/trigger./i \    // Deploy endpoint (Manajer-only, deploy secret required)\n    Route::post("/admin/deploy", DeployController::class)->middleware("role:Manajer");' routes/api.php
    echo "Deploy route added"
else
    echo "Deploy route already exists"
fi

# Add use statement for DeployController
if ! grep -q "use App\\\\Http\\\\Controllers\\\\Api\\\\DeployController;" routes/api.php; then
    sed -i '/use App\\\\Http\\\\Controllers\\\\Api\\\\DailyReportController;/a use App\\Http\\Controllers\\Api\\DeployController;' routes/api.php
    echo "DeployController import added"
fi

echo "=== 4. Clear cache & reload ==="
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
sudo systemctl reload nginx

echo ""
echo "=== DEPLOY COMPLETE ==="
echo "DEPLOY_SECRET: $DEPLOY_SECRET"
echo ""
echo "Dari Windows, deploy via:"
echo "  curl -X POST http://43.133.142.74/api/admin/deploy \\"
echo "    -H 'Authorization: Bearer <MANAJER_TOKEN>' \\"
echo "    -H 'X-Deploy-Secret: $DEPLOY_SECRET' \\"
echo "    -d '{\"archive\":\"<base64_tar.gz>\"}'"
echo ""
echo "Health check:"
curl -s http://localhost/api/health
