<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;

class BackupController extends Controller
{
    /**
     * POST /api/backup/trigger
     * Role: Manajer
     *
     * Runs the project backup script synchronously. We intentionally use
     * Symfony Process (bundled with Laravel) so we get a real stream object
     * and a sane timeout. The VPS path is hard-coded because the script
     * also assumes it (sqlite3 + tar to /var/backups/sim-kk); on a
     * non-VPS box the script will simply fail, which we surface as
     * success=false in the response (we still return 200 so the UI can
     * render the log without a generic HTTP error toast).
     */
    public function trigger(Request $request): JsonResponse
    {
        $projectRoot = config('sim-kk.backup.project_root', '/var/www/sim-kk');
        $scriptPath  = $projectRoot . '/.workflow/sim-kk-backup.sh';

        $startedAt  = CarbonImmutable::now();
        $log        = [];
        $success    = false;
        $exitCode   = null;
        $errorMsg   = null;

        if (!is_file($scriptPath)) {
            return response()->json([
                'success'     => false,
                'log'         => ["Backup script not found at {$scriptPath}."],
                'output'      => '',
                'finished_at' => $startedAt->toIso8601String(),
                'started_at'  => $startedAt->toIso8601String(),
            ]);
        }

        $process = new Process(['bash', $scriptPath], $projectRoot, null, null, 600.0);
        $process->setTimeout(600);

        try {
            $process->run(function ($type, $buffer) use (&$log) {
                $stream = Process::STDOUT === $type ? 'OUT' : 'ERR';
                foreach (preg_split('/\R/', rtrim($buffer, "\n")) as $line) {
                    if ($line === '') {
                        continue;
                    }
                    $log[] = "[{$stream}] " . $line;
                }
            });

            $exitCode = $process->getExitCode();
            $success  = $process->isSuccessful();
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $log[] = '[ERR] ' . $errorMsg;
        }

        $finishedAt = CarbonImmutable::now();

        return response()->json([
            'success'     => $success,
            'exit_code'   => $exitCode,
            'error'       => $errorMsg,
            'log'         => $log,
            'output'      => $process instanceof Process ? $process->getOutput() . $process->getErrorOutput() : '',
            'started_at'  => $startedAt->toIso8601String(),
            'finished_at' => $finishedAt->toIso8601String(),
            'duration_s'  => $finishedAt->diffInSeconds($startedAt),
        ]);
    }
}
