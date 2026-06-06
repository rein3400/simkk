<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditService
{
    public function log(
        ?User $user,
        string $aksi,
        string $entitas,
        ?string $entitasId = null,
        ?array $dataLama = null,
        ?array $dataBaru = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id'    => $user?->id,
            'aksi'       => $aksi,
            'entitas'    => $entitas,
            'entitas_id' => $entitasId,
            'data_lama'  => $dataLama,
            'data_baru'  => $dataBaru,
            'ip_address' => request()?->ip(),
        ]);
    }
}
