<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Secure deploy endpoint — receives a base64-encoded tar.gz, extracts it,
 * runs migrations, clears caches. Protected by:
 *   1. Sanctum auth (Manajer role only, via route middleware)
 *   2. DEPLOY_SECRET header (X-Deploy-Secret) — must match config value
 *   3. Idempotent — safe to call multiple times
 *
 * Route: POST /api/admin/deploy
 */
class DeployController extends Controller
{
    private const DEPLOY_DIR = '/var/www/sim-kk';

    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('sim-kk.deploy_secret');
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

        // Write tar.gz to /tmp
        $tmpPath = '/tmp/simkk-deploy-' . time() . '.tar.gz';
        file_put_contents($tmpPath, $bytes);

        $stageDir = '/tmp/simkk-stage-' . time();
        @mkdir($stageDir, 0755, true);

        $log = [];
        $log[] = "Stage: extracting {$tmpPath} to {$stageDir}";

        $extract = SymfonyProcess::fromShellCommandline("tar -xzf {$tmpPath} -C {$stageDir}");
        $extract->setTimeout(120);
        $extract->run();
        if (!$extract->isSuccessful()) {
            return response()->json(['message' => 'Extract failed: ' . $extract->getErrorOutput()], 500);
        }

        $log[] = "Stage: copying files to " . self::DEPLOY_DIR;

        // Copy API code (preserving existing .env, .git, storage, vendor)
        $copyCmds = [
            "cp -r {$stageDir}/app/* " . self::DEPLOY_DIR . "/apps/api/app/",
            "cp -r {$stageDir}/config/* " . self::DEPLOY_DIR . "/apps/api/config/",
            "cp -r {$stageDir}/database/* " . self::DEPLOY_DIR . "/apps/api/database/",
            "cp -r {$stageDir}/routes/* " . self::DEPLOY_DIR . "/apps/api/routes/",
            "cp -r {$stageDir}/resources/* " . self::DEPLOY_DIR . "/apps/api/resources/ 2>/dev/null || true",
            "cp {$stageDir}/composer.json " . self::DEPLOY_DIR . "/apps/api/ 2>/dev/null || true",
            "cp {$stageDir}/composer.lock " . self::DEPLOY_DIR . "/apps/api/ 2>/dev/null || true",
            "cp {$stageDir}/artisan " . self::DEPLOY_DIR . "/apps/api/ 2>/dev/null || true",
            // Vue dist
            "rm -rf " . self::DEPLOY_DIR . "/apps/web/dist",
            "cp -r {$stageDir}/dist " . self::DEPLOY_DIR . "/apps/web/dist",
            // Permissions
            "chown -R www-data:www-data " . self::DEPLOY_DIR . "/apps",
            "chmod -R 755 " . self::DEPLOY_DIR . "/apps",
        ];
        foreach ($copyCmds as $cmd) {
            $p = SymfonyProcess::fromShellCommandline($cmd);
            $p->setTimeout(60);
            $p->run();
            if (!$p->isSuccessful()) {
                $log[] = "WARN: $cmd — " . $p->getErrorOutput();
            }
        }

        // Run migrations
        $log[] = "Stage: running migrations";
        $migrate = SymfonyProcess::fromShellCommandline(
            "cd " . self::DEPLOY_DIR . "/apps/api && php artisan migrate --force --no-interaction"
        );
        $migrate->setTimeout(120);
        $migrate->run();
        $log[] = "Migrate: " . ($migrate->isSuccessful() ? "OK" : $migrate->getErrorOutput());

        // Clear caches
        $cacheClears = ['config:clear', 'route:clear', 'cache:clear', 'view:clear'];
        foreach ($cacheClears as $cmd) {
            $p = SymfonyProcess::fromShellCommandline(
                "cd " . self::DEPLOY_DIR . "/apps/api && php artisan {$cmd}"
            );
            $p->setTimeout(30);
            $p->run();
        }

        // Reload nginx
        $reload = SymfonyProcess::fromShellCommandline("systemctl reload nginx");
        $reload->setTimeout(10);
        $reload->run();
        $log[] = "Nginx reload: " . ($reload->isSuccessful() ? "OK" : $reload->getErrorOutput());

        // Health check
        $health = SymfonyProcess::fromShellCommandline("curl -s http://localhost/api/health");
        $health->setTimeout(10);
        $health->run();
        $log[] = "Health: " . $health->getOutput();

        // Cleanup
        @unlink($tmpPath);
        $cleanup = SymfonyProcess::fromShellCommandline("rm -rf {$stageDir}");
        $cleanup->run();

        return response()->json([
            'deployed' => true,
            'log' => $log,
        ]);
    }
}
