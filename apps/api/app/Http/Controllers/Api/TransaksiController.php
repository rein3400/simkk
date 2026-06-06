<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IdempotencyKey;
use App\Services\TransaksiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransaksiController extends Controller
{
    public function pay(Request $request, TransaksiService $service): JsonResponse
    {
        $validated = $request->validate([
            'pasien_id'      => 'required|integer|exists:pasien,id',
            'terapis_id'     => 'required|integer|exists:terapis,id',
            'items'          => 'required|array|min:1',
            'items.*.serviceId' => 'required|integer|exists:layanan,id',
            'items.*.qty'    => 'nullable|integer|min:1|max:9999',
            'diskon'         => 'nullable|integer|min:0',
            'metode_bayar'   => 'nullable|string|max:32',
        ]);

        // F-006 fix: Idempotency-Key replay protection.
        // Same (user, key, endpoint) returns the cached response, no duplicate TRX.
        $rawKey = $request->header('Idempotency-Key');
        if (is_string($rawKey) && $rawKey !== '') {
            if (!preg_match('/^[A-Za-z0-9._-]{8,128}$/', $rawKey)) {
                return response()->json([
                    'message' => 'Idempotency-Key must be 8-128 chars of [A-Za-z0-9._-].',
                ], 422);
            }
            $keyHash = hash('sha256', $rawKey);
            $cached = IdempotencyKey::where('user_id', $request->user()->id)
                ->where('key_hash', $keyHash)
                ->where('endpoint', 'transactions.pay')
                ->first();
            if ($cached) {
                return response()->json($cached->response_body, $cached->status_code);
            }
        }

        $result = $service->pay(
            $validated['pasien_id'],
            $validated['terapis_id'],
            $validated['items'],
            $validated['diskon'] ?? 0,
            $validated['metode_bayar'] ?? 'Tunai',
        );

        $status = 201;
        $body = $result;

        if (is_string($rawKey) && $rawKey !== '') {
            IdempotencyKey::create([
                'user_id'       => $request->user()->id,
                'key_hash'      => $keyHash,
                'endpoint'      => 'transactions.pay',
                'status_code'   => $status,
                'response_body' => $body,
            ]);
        }

        return response()->json($body, $status);
    }
}
