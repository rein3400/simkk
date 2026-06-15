<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BatchStok;
use App\Models\PembelianSupplier;
use App\Services\AuditService;
use App\Services\InventarisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarisController extends Controller
{
    public function addPurchase(Request $request, InventarisService $service, AuditService $audit): JsonResponse
    {
        $validated = $request->validate([
            'produk_id'   => 'required|integer|exists:produk,id',
            // Per revisi R3 — supplier is now a registered master. Legacy
            // `supplier` string still accepted for backward compat but
            // `supplier_id` is preferred and must reference the master.
            'supplier_id' => 'required|integer|exists:supplier,id',
            'supplier'    => ['nullable', 'string', 'max:60', 'regex:/^[^<>"\\x00-\\x1F]+$/'],
            // P2 #9: kode_batch must be safe ASCII (no slashes, no `..` segments).
            'kode_batch'  => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/', 'not_regex:/\.\./'],
            'qty'         => 'required|integer|min:1',
            'hpp'         => 'required|integer|min:0',
            'kadaluarsa'  => 'nullable|date|after:today',
        ]);

        $result = $service->addPurchase($validated);
        $audit->log($request->user(), 'purchase.create', 'pembelian_supplier', $validated['kode_batch'], null, [
            'produk_id' => $validated['produk_id'],
            'kode_batch' => $validated['kode_batch'],
            'qty' => $validated['qty'],
        ]);
        return response()->json($result, 201);
    }

    public function deleteBatch(Request $request, int $batch, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        $batchRow = BatchStok::findOrFail($batch);

        // Refuse deletion if stock already consumed by a transaction
        $consumed = DB::table('stok_mutasi')
            ->where('produk_id', $batchRow->produk_id)
            ->where('reference', 'LIKE', '%' . $batchRow->kode_batch . '%')
            ->where('tipe', 'KELUAR')
            ->sum('qty');
        if ($consumed > 0) {
            return response()->json([
                'message' => "Batch ini sudah pernah dipakai di transaksi (qty keluar: {$consumed}). Tidak bisa dihapus; gunakan adjustment manual.",
            ], 422);
        }

        // Reverse the original IN stock mutation if present
        DB::table('stok_mutasi')
            ->where('produk_id', $batchRow->produk_id)
            ->where('tipe', 'MASUK')
            ->where('reference', 'LIKE', '%' . $batchRow->kode_batch . '%')
            ->delete();

        // Soft-delete the batch via stock_mutation reversal — we keep row for audit, set qty=0
        $batchRow->update(['qty' => 0]);

        $audit->log($user, 'purchase.delete', 'batch_stok', (string) $batchRow->id, [
            'produk_id' => $batchRow->produk_id,
            'kode_batch' => $batchRow->kode_batch,
            'qty' => $batchRow->getOriginal('qty'),
        ], [
            'produk_id' => $batchRow->produk_id,
            'kode_batch' => $batchRow->kode_batch,
            'qty' => 0,
        ]);

        return response()->json(['deleted' => true]);
    }
}
