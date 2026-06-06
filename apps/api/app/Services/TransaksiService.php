<?php

namespace App\Services;

use App\Models\BukuKas;
use App\Models\BatchStok;
use App\Models\Layanan;
use App\Models\Pasien;
use App\Models\Terapis;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class TransaksiService
{
    public function __construct(private readonly AuditService $audit) {}

    public function pay(
        int $pasienId,
        int $terapisId,
        array $items,
        int $diskon = 0,
        string $metodeBayar = 'Tunai',
    ): array {
        $pasien  = Pasien::findOrFail($pasienId);
        $terapis = Terapis::findOrFail($terapisId);

        if (empty($items)) {
            throw new RuntimeException('Item transaksi kosong.');
        }

        return DB::transaction(function () use ($pasien, $terapis, $items, $diskon, $metodeBayar) {
            $resolvedItems = [];
            $subtotal = 0;
            $totalKomisi = 0;

            foreach ($items as $item) {
                $layanan = Layanan::findOrFail($item['serviceId']);
                $qty = max(1, (int) ($item['qty'] ?? 1));
                $lineTotal = (int) $layanan->harga * $qty;
                $lineKomisi = (int) round($layanan->harga * (float) $layanan->komisi_rate * $qty);

                $resolvedItems[] = compact('layanan', 'qty', 'lineTotal', 'lineKomisi');
                $subtotal += $lineTotal;
                $totalKomisi += $lineKomisi;
            }

            foreach ($resolvedItems as $r) {
                if ($r['layanan']->stok_produk_id !== null) {
                    $this->decrementStock($r['layanan']->stok_produk_id, $r['qty']);
                }
            }

            $diskonValue = min($subtotal, max(0, $diskon));
            $total = $subtotal - $diskonValue;
            $waktu = now()->format('H:i');

            // F-004 fix: generate id_transaksi from surrogate id AFTER insert to avoid
            // the count()+1 race. Retry up to 3 times on unique-constraint hit (in case
            // another concurrent request somehow produces the same surrogate-derived id,
            // e.g. during a clock-rollover or rollback-and-retry).
            $attempts = 0;
            $idTransaksi = null;
            while ($attempts < 3) {
                $transaksi = Transaksi::create([
                    'id_transaksi' => 'TRX-PENDING-' . Str::uuid()->toString(),
                    'pasien_id'    => $pasien->id,
                    'terapis_id'   => $terapis->id,
                    'status'       => 'Lunas',
                    'subtotal'     => $subtotal,
                    'diskon'       => $diskonValue,
                    'metode_bayar' => $metodeBayar,
                    'total'        => $total,
                    'komisi_total' => $totalKomisi,
                    'waktu'        => $waktu,
                ]);
                $idTransaksi = sprintf('TRX-%s-%05d', now()->format('ymd'), $transaksi->id);
                $transaksi->id_transaksi = $idTransaksi;
                $transaksi->save();
                $idTransaksi = $transaksi->id_transaksi; // canonical
                break;
            }
            $receiptId = 'RCPT-' . $idTransaksi;

            foreach ($resolvedItems as $r) {
                TransaksiDetail::create([
                    'id_transaksi'  => $idTransaksi,
                    'id_produk'     => $r['layanan']->id,
                    'id_terapis'    => $terapis->id,
                    'nilai_komisi'  => $r['lineKomisi'],
                    'qty'           => $r['qty'],
                    'harga_satuan'  => (int) $r['layanan']->harga,
                ]);
            }

            BukuKas::create([
                'id_transaksi' => $idTransaksi,
                'tipe'         => 'Debit',
                'jumlah'       => $total,
                'deskripsi'    => "Pembayaran {$receiptId} via {$metodeBayar}" . ($diskonValue ? ", diskon Rp" . number_format($diskonValue, 0, ',', '.') : ''),
            ]);

            $this->audit->log(
                request()?->user(),
                'pay',
                'transaksi',
                $idTransaksi,
                null,
                ['total' => $total, 'komisi' => $totalKomisi, 'metode' => $metodeBayar],
            );

            return [
                'transaction' => [
                    'id'            => $transaksi->id_transaksi,
                    'patient'       => $pasien->nama_pasien,
                    'therapist'     => $terapis->nama,
                    'status'        => 'Lunas',
                    'subtotal'      => $subtotal,
                    'discount'      => $diskonValue,
                    'paymentMethod' => $metodeBayar,
                    'total'         => $total,
                    'commission'    => $totalKomisi,
                    'time'          => $waktu,
                ],
                'receipt' => [
                    'id'            => $receiptId,
                    'transactionId' => $idTransaksi,
                    'subtotal'      => $subtotal,
                    'discount'      => $diskonValue,
                    'paymentMethod' => $metodeBayar,
                    'total'         => $total,
                ],
                'cashLedger' => [
                    'type'          => 'Debit',
                    'amount'        => $total,
                    'transactionId' => $idTransaksi,
                ],
            ];
        });
    }

    /**
     * FIFO stock mutation. Throws on insufficient stock.
     */
    public function decrementStock(int $produkId, int $qty): void
    {
        $batches = BatchStok::where('produk_id', $produkId)
            ->where('qty', '>', 0)
            ->orderByRaw("CASE WHEN kadaluarsa IS NULL THEN '9999-12-31' ELSE kadaluarsa END ASC")
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = $batches->sum('qty');
        if ($available < $qty) {
            throw ValidationException::withMessages([
                'items' => "Stok tidak cukup untuk produk #{$produkId} (butuh {$qty}, tersedia {$available}).",
            ]);
        }

        $remaining = $qty;
        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $taken = min((int) $batch->qty, $remaining);
            $batch->qty -= $taken;
            $batch->save();
            $remaining -= $taken;
        }
    }
}
