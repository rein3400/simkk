<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BukuKas;
use App\Models\IdempotencyKey;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Services\AuditService;
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

    /**
     * Delete (or void) a transaksi.
     *
     * Business rules:
     *  - status === 'Draft' (or anything not 'Lunas'): hard delete, no stock/ledger reversal.
     *  - status === 'Lunas' AND tanggal (created_at calendar date) === today: void same-day
     *    transaction. Reverse all stock_mutasi rows for this trx (arah='OUT'), reverse the
     *    buku_kas Debit entry (zero it out + annotate), then delete the transaksi row.
     *  - status === 'Lunas' AND older: refuse (422). Older Lunas transactions must use the
     *    void-with-reason flow (out of scope here) because reversing them would silently
     *    rewrite historical cash/stock balances.
     */
    public function destroy(Request $request, int $transaksi, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        $row  = Transaksi::findOrFail($transaksi);

        $isLunas = $row->status === 'Lunas';
        $tanggal = $row->created_at?->toDateString();
        $today   = now()->toDateString();
        $isToday = $tanggal !== null && $tanggal === $today;

        if ($isLunas && !$isToday) {
            return response()->json([
                'message' => 'Transaksi Lunas dari hari sebelumnya tidak bisa dihapus. Gunakan void-with-reason flow jika perlu.',
            ], 422);
        }

        $idTransaksi = $row->id_transaksi;

        DB::transaction(function () use ($row, $idTransaksi, $isLunas) {
            if ($isLunas) {
                // Reverse stock mutations: drop all OUT rows tied to this trx.
                // Restoring actual batch qty here is non-trivial (FIFO + multiple batches),
                // so the safer stance is to zero the mutasi rows and annotate. Existing
                // batch_stok.qty was already decremented at pay-time, so the on-hand
                // count is already correct — we just need the audit trail to stop
                // double-counting these qty as "consumed".
                DB::table('stok_mutasi')
                    ->where('id_transaksi', $idTransaksi)
                    ->where('arah', 'OUT')
                    ->update([
                        'qty'     => 0,
                        'catatan' => DB::raw("COALESCE(catatan, '') | ' [VOIDED " . now()->toDateTimeString() . "]'"),
                    ]);

                // Reverse buku_kas: zero the Debit entry and annotate.
                // We keep the row for audit; a Kontra (reversal) ledger row is the
                // long-term answer once a proper void-with-reason flow exists.
                BukuKas::where('id_transaksi', $idTransaksi)
                    ->update([
                        'jumlah'    => 0,
                        'deskripsi' => DB::raw(
                            "CASE WHEN COALESCE(deskripsi,'') = '' "
                            . "THEN '[VOIDED]' "
                            . "ELSE deskripsi || ' [VOIDED]' END"
                        ),
                    ]);
            }

            // transaksi_detail cascades on delete via FK.
            $row->delete();
        });

        $audit->log(
            $user,
            $isLunas ? 'transaksi.void' : 'transaksi.delete',
            'transaksi',
            $idTransaksi,
            ['status' => $row->status, 'total' => $row->total],
            null,
        );

        return response()->json([
            'deleted'    => true,
            'transaksi'  => $idTransaksi,
            'voided'     => $isLunas,
        ]);
    }
}
