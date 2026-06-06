<?php

namespace App\Services;

use App\Models\BatchStok;
use App\Models\PembelianSupplier;
use App\Models\Produk;
use Illuminate\Support\Facades\DB;

class InventarisService
{
    public function __construct(private readonly AuditService $audit) {}

    public function addPurchase(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $produk = Produk::findOrFail($data['produk_id']);

            $pembelian = PembelianSupplier::create($data);
            $batch = BatchStok::create($data);

            $this->audit->log(
                request()?->user(),
                'add_purchase',
                'batch_stok',
                (string) $batch->id,
                null,
                $data,
            );

            return [
                'id'         => $produk->id,
                'name'       => $produk->nama,
                'category'   => $produk->kategori,
                'totalStock' => (int) BatchStok::where('produk_id', $produk->id)->sum('qty'),
                'newBatch'   => [
                    'code'    => $batch->kode_batch,
                    'qty'     => (int) $batch->qty,
                    'hpp'     => (int) $batch->hpp,
                    'expiry'  => $batch->kadaluarsa?->format('Y-m-d') ?? 'Reusable',
                    'supplier'=> $batch->supplier,
                ],
            ];
        });
    }
}
