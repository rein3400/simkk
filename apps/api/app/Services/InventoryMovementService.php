<?php

namespace App\Services;

use App\Models\Produk;
use App\Models\StokMutasi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Aggregates `stok_mutasi` into a per-product, per-day range report.
 * Output rows mirror the static HTML preview columns:
 *   Item Code · Item Name · Beginning Balance · Purchase (IN) · Return Sales (IN)
 *   · Barang Masuk (IN) · Return Purchase (OUT) · Sales (OUT) · Real Sales (OUT)
 *   · Barang Keluar (OUT) · Ending Balance
 *
 * Beginning is the cumulative balance as of the day BEFORE $from.
 * Ending = Beginning + INs - OUTs.
 */
class InventoryMovementService
{
    /**
     * Query inventory movement rows for a date range.
     *
     * @return Collection<int,array{
     *     id_produk:int, kode:string, nama:string, kategori:?string,
     *     beginning:float, purchase_in:float, return_sales_in:float, barang_masuk_in:float,
     *     return_purchase_out:float, sales_out:float, real_sales_out:float, barang_keluar_out:float,
     *     ending:float
     * }>
     */
    public function query(string $from, string $to): Collection
    {
        $start    = CarbonImmutable::parse($from)->startOfDay();
        $end      = CarbonImmutable::parse($to)->endOfDay();
        $prevEnd  = $start->subDay()->endOfDay();

        $produkList = Produk::query()->orderBy('nama')->get();
        if ($produkList->isEmpty()) {
            return collect();
        }

        // Beginning balance: net qty of all mutations before $start, per product.
        // StokMutasi.tanggal is cast to date but stored as datetime in SQLite; use
        // raw `date(tanggal)` to strip the time component for a clean comparison.
        $beginnings = StokMutasi::query()
            ->whereRaw('date(tanggal) <= ?', [$prevEnd->toDateString()])
            ->selectRaw('id_produk, SUM(CASE WHEN arah = ? THEN qty ELSE -qty END) AS bal', [StokMutasi::ARAH_IN])
            ->groupBy('id_produk')
            ->pluck('bal', 'id_produk');

        // In-window pivot: sum qty per (id_produk, tipe) inside the range.
        $pivot = StokMutasi::query()
            ->whereRaw('date(tanggal) >= ?', [$start->toDateString()])
            ->whereRaw('date(tanggal) <= ?', [$end->toDateString()])
            ->selectRaw('id_produk, tipe, SUM(qty) AS qty')
            ->groupBy('id_produk', 'tipe')
            ->get()
            ->groupBy('id_produk');

        return $produkList->map(function (Produk $p) use ($beginnings, $pivot) {
            $rows = $pivot->get($p->id, collect());

            $sumTipe = fn (string $tipe) => (float) ($rows->firstWhere('tipe', $tipe)?->qty ?? 0);

            $purchaseIn         = $sumTipe(StokMutasi::TIPE_PEMBELIAN);
            $returnSalesIn      = $sumTipe(StokMutasi::TIPE_RETURN_SALES);
            // "Barang Masuk" is manual stock entry distinct from supplier purchases.
            // No dedicated tipe exists today, so it's reported as 0 by default;
            // the future manual-entry tipe will plug into this same column.
            $barangMasukIn      = 0.0;
            $returnPurchaseOut  = $sumTipe(StokMutasi::TIPE_RETURN_PURCHASE);
            $salesOut           = $sumTipe(StokMutasi::TIPE_SALES);
            $barangKeluarOut    = $sumTipe(StokMutasi::TIPE_BARANG_KELUAR);

            $beginning = (float) ($beginnings[$p->id] ?? 0);
            // Real Sales is a presentation-only alias of Sales (same underlying
            // qty in the spec). Including it in the formula would double-count.
            $realSalesOut = $salesOut;
            $ending       = $beginning + $purchaseIn + $returnSalesIn + $barangMasukIn
                          - $returnPurchaseOut - $salesOut - $barangKeluarOut;

            return [
                'id_produk'            => (int) $p->id,
                'kode'                 => $this->kodeFor($p),
                'nama'                 => (string) $p->nama,
                'kategori'             => $p->kategori,
                'beginning'            => round($beginning, 2),
                'purchase_in'          => round($purchaseIn, 2),
                'return_sales_in'      => round($returnSalesIn, 2),
                'barang_masuk_in'      => round($barangMasukIn, 2),
                'return_purchase_out'  => round($returnPurchaseOut, 2),
                'sales_out'            => round($salesOut, 2),
                'real_sales_out'       => round($realSalesOut, 2),
                'barang_keluar_out'    => round($barangKeluarOut, 2),
                'ending'               => round($ending, 2),
            ];
        })->values();
    }

    /**
     * Heuristic: most seeded products have an explicit `kode` style identifier
     * (e.g. "BCF..." in the static HTML preview). When the Produk table has no
     * `kode` column, derive a stable 3-letter code from the name.
     */
    private function kodeFor(Produk $p): string
    {
        if (array_key_exists('kode', $p->getAttributes()) && !empty($p->getAttributes()['kode'])) {
            return strtoupper((string) $p->getAttributes()['kode']);
        }
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $p->nama));
        $code  = substr($clean, 0, 3);
        return $code . '-' . str_pad((string) $p->id, 4, '0', STR_PAD_LEFT);
    }
}
