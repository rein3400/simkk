<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * GET /api/audit-logs
     * Query params:
     *   - limit  (int, default 50, max 500)
     *   - action (string, exact match on AuditLog.aksi)
     *   - user_id (int)
     * Role: Manajer
     */
    public function index(Request $request): JsonResponse
    {
        $limit  = (int) $request->query('limit', 50);
        if ($limit < 1)   { $limit = 50; }
        if ($limit > 500) { $limit = 500; }

        $query = AuditLog::query()
            ->with(['user:id,username,nama_lengkap'])
            ->orderByDesc('id');

        $action = trim((string) $request->query('action', ''));
        if ($action !== '') {
            $query->where('aksi', $action);
        }

        $userId = $request->query('user_id');
        if ($userId !== null && $userId !== '') {
            $query->where('user_id', (int) $userId);
        }

        $rows = $query->limit($limit)->get()->map(function (AuditLog $log) {
            $user = $log->user;
            return [
                'id'           => (int) $log->id,
                'username'     => $user?->username,
                'nama_lengkap' => $user?->nama_lengkap,
                'action'       => $log->aksi,
                'entitas'      => $log->entitas,
                'entitas_id'   => $log->entitas_id,
                'payload'      => $log->data_baru ?? $log->data_lama,
                'ip_address'   => $log->ip_address,
                'created_at'   => optional($log->created_at)?->toIso8601String(),
            ];
        });

        return response()->json([
            'count' => $rows->count(),
            'rows'  => $rows,
        ]);
    }
}
