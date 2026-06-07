<?php

namespace App\Services;

use App\Models\DailyCashFloat;
use App\Models\DailyClosing;
use App\Models\Produk;
use App\Models\Transaksi;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

/**
 * Aggregate one-day transactional data and render a Dompdf PDF that mirrors
 * the Naavagreen-format daily report (8 sections, dual TTD bottom-right).
 *
 * Inputs are derived from `transaksi` (status Lunas) joined with
 * `transaksi_detail` and `produk`. The flow is intentionally read-only so it
 * can be re-run idempotently by `export()` and `submit()` alike.
 */
class DailyReportService
{
    public function __construct()
    {
    }

    private function kopName(): string
    {
        return (string) (config('sim-kk.clinic.name') ?? 'KLINIK KECANTIKAN SIM-KK');
    }

    private function kopAddress(): string
    {
        return (string) (config('sim-kk.clinic.address') ?? 'Jl. Operasional Klinik No. 25, Samarinda');
    }

    public static function fromConfig(): self
    {
        return new self();
    }

    /**
     * Aggregate the daily report payload for a given date string (Y-m-d).
     * Returns an array ready to feed to the Blade view.
     */
    public function buildPayload(string $tanggal): array
    {
        $date     = CarbonImmutable::parse($tanggal)->startOfDay();
        $dateEnd  = $date->endOfDay();

        // 1. Cash at cashier: prefer daily_cash_float for the first kasir, else 0.
        $float = DailyCashFloat::whereDate('tanggal', $date)->orderBy('id')->first();
        $modalAwal = (int) ($float?->modal_awal ?? 0);

        // 2. Pull all Lunas transaksi for that day. `waktu` is time-of-day, so
        //    we use created_at for date filtering.
        $transaksis = Transaksi::query()
            ->where('status', 'Lunas')
            ->whereBetween('created_at', [$date, $dateEnd])
            ->with(['details.layanan' => function ($q) {
                $q->select('id', 'nama', 'kategori');
            }])
            ->orderBy('id')
            ->get();

        $totalTunai = 0;
        $totalCard  = 0;
        $rounding   = 0;
        $vatSales   = 0;
        $netSalesByCategory = $this->seedCategoryRows();

        $cardBreakdownLeft  = [];
        $cardBreakdownRight = [];

        foreach ($transaksis as $t) {
            $total = (int) $t->total;
            $metode = strtoupper((string) $t->metode_bayar);
            if ($metode === 'TUNAI') {
                $totalTunai += $total;
            } else {
                $totalCard += $total;
                [$left, $right] = $this->classifyCard($metode, $total);
                $cardBreakdownLeft[]  = $left;
                $cardBreakdownRight[] = $right;
            }

            // Distribute to category by proportional split (default: by detail).
            $detailSum = (int) $t->details->sum(fn ($d) => (int) $d->harga_satuan * (int) $d->qty);
            if ($detailSum === 0) {
                // No detail rows (or zero qty): book the entire total to a
                // generic bucket so the report still shows a number.
                $kat = 'Layanan';
                $netSalesByCategory[$kat] = ($netSalesByCategory[$kat] ?? 0) + $total;
                continue;
            }
            foreach ($t->details as $d) {
                $nilai = (int) round(((int) $d->harga_satuan * (int) $d->qty) * ($total / max(1, $detailSum)));
                $kat = $d->layanan?->kategori ?? 'Layanan';
                $netSalesByCategory[$kat] = ($netSalesByCategory[$kat] ?? 0) + $nilai;
            }
        }

        $totalSales = array_sum($netSalesByCategory);
        $pnl        = (int) ($totalTunai + $totalCard - $rounding);

        $cashOut     = max(0, $totalTunai - $modalAwal);
        $endOfDay    = $modalAwal; // cash leftover at cashier = modal awal (cash float restored)
        $setoranBank = $totalCard > 0 ? (int) $totalCard : 0;
        $totalExpend = 0;

        $closing = DailyClosing::whereDate('tanggal', $date)->first();
        $signerManajer = $closing?->manajer?->nama_lengkap ?? '';
        $signerKasir   = $closing?->kasir?->nama_lengkap   ?? '';

        $signatureManajerUrl = $this->resolveSignaturePath($closing?->manajer?->signature_path);
        $signatureKasirUrl   = $this->resolveSignaturePath($closing?->kasir?->signature_path);

        // Fallback to placeholder if user has no signature yet.
        if ($signatureManajerUrl === null) {
            $signatureManajerUrl = $this->placeholderUrl('manajer');
        }
        if ($signatureKasirUrl === null) {
            $signatureKasirUrl = $this->placeholderUrl('kasir');
        }

        return [
            'kopName'             => $this->kopName(),
            'kopAddress'          => $this->kopAddress(),
            'branch'              => 'NGI-SMD01',
            'tanggal'             => $date->toDateString(),
            'dateLabel'           => $date->format('d/m/Y'),
            'dayName'             => $date->format('l'),
            'cashAtCashier'       => ['modal_awal' => $modalAwal],
            'netSalesByCategory'  => $netSalesByCategory,
            'totalSales'          => $totalSales,
            'rounding'            => $rounding,
            'vatSales'            => $vatSales,
            'cardBreakdown'       => [
                'left'  => $cardBreakdownLeft,
                'right' => $cardBreakdownRight,
            ],
            'totalCard'           => $totalCard,
            'totalBranchDeposit'  => 0,
            'totalUlpt'           => 0,
            'totalBranchExpend'   => 0,
            'totalRpj'            => 0,
            'totalDownPayment'    => 0,
            'totalPelunasan'      => 0,
            'totalExpend'         => $totalExpend,
            'pnl'                 => $pnl,
            'cashOut'             => $cashOut,
            'endOfDay'            => $endOfDay,
            'setoranBank'         => $setoranBank,
            'signerManajer'       => $signerManajer,
            'signerKasir'         => $signerKasir,
            'signatureManajerUrl' => $signatureManajerUrl,
            'signatureKasirUrl'   => $signatureKasirUrl,
        ];
    }

    /**
     * Render the PDF binary for a given date. Dompdf-only, no remote fetching.
     */
    public function generate(string $tanggal): string
    {
        $payload = $this->buildPayload($tanggal);

        $html = View::make('reports.daily', $payload + [
            'idr' => fn (int $n) => 'Rp' . number_format($n, 0, ',', '.'),
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans Mono');
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $pdf->render();
        return $pdf->output();
    }

    /**
     * Create or update the daily_closing row for the given date in 'submitted'
     * state, signed by the requesting kasir. Returns the model.
     */
    public function submit(string $tanggal, int $userKasirId): DailyClosing
    {
        $date = CarbonImmutable::parse($tanggal)->toDateString();
        $payload = $this->buildPayload($date);

        return DailyClosing::updateOrCreate(
            ['tanggal' => $date],
            [
                'user_kasir_id'     => $userKasirId,
                'submitted_at'      => now(),
                'status'            => DailyClosing::STATUS_SUBMITTED,
                'total_penjualan'   => (int) $payload['totalSales'],
                'total_card'        => (int) $payload['totalCard'],
                'total_tunai'       => (int) ($payload['totalSales'] - $payload['totalCard']),
                'pnl'               => (int) $payload['pnl'],
                'setoran_bank'      => (int) $payload['setoranBank'],
            ],
        );
    }

    /**
     * Move a submitted closing into 'approved' state with the manajer signature
     * stamped. Returns the model.
     */
    public function approve(int $closingId, int $userManajerId): DailyClosing
    {
        $closing = DailyClosing::findOrFail($closingId);
        if ($closing->status !== DailyClosing::STATUS_SUBMITTED) {
            abort(422, 'Hanya closing berstatus submitted yang bisa di-approve.');
        }
        $manajer = \App\Models\User::findOrFail($userManajerId);
        $closing->fill([
            'user_manajer_id'       => $userManajerId,
            'approved_at'           => now(),
            'status'                => DailyClosing::STATUS_APPROVED,
            'signature_manajer_path'=> $manajer->signature_path,
        ])->save();
        return $closing;
    }

    private function seedCategoryRows(): array
    {
        // Stable key order matches the seed Produk categories + Naavagreen fallback.
        $seed = ['Skincare', 'Treatment', 'Alat', 'Consumable', 'Layanan', 'Tindakan Dokter'];
        $out = [];
        foreach ($seed as $k) {
            $out[$k] = 0;
        }
        // Also seed any categories the user added later.
        Produk::query()->whereNotNull('kategori')->distinct()->pluck('kategori')
            ->each(function ($k) use (&$out) {
                if (!array_key_exists($k, $out)) {
                    $out[$k] = 0;
                }
            });
        return $out;
    }

    /**
     * Map a metode_bayar value into a 2-column (bank | non-cash detail) row.
     * The convention follows the design spec section 0: Transfer/EDC/QRIS prefixes
     * resolve to a (Bank X (N), DEBIT/QR/TRANSFER X) pair.
     */
    private function classifyCard(string $metode, int $nilai): array
    {
        $map = [
            'TRANSFER BCA'      => ['BANK BCA',          'TRANSFER'],
            'TRANSFER MANDIRI'  => ['BANK MANDIRI',      'TRANSFER'],
            'EDC BCA'           => ['BANK BCA',          'DEBIT BCA'],
            'EDC BCA KASIR'     => ['EDC BCA KASIR',     'DEBIT BCA'],
            'EDC MANDIRI'       => ['BANK MANDIRI',      'DEBIT MANDIRI'],
            'EDC MANDIRI KASIR' => ['EDC MANDIRI KASIR', 'DEBIT MANDIRI'],
            'QRIS BCA'          => ['BANK BCA',          'QR BCA'],
            'QRIS MANDIRI'      => ['BANK MANDIRI',      'QR MANDIRI'],
        ];
        $key = strtoupper(trim($metode));
        if (isset($map[$key])) {
            return [
                ['label' => $map[$key][0], 'nilai' => $nilai],
                ['label' => $map[$key][1], 'nilai' => $nilai],
            ];
        }
        return [
            ['label' => $key, 'nilai' => $nilai],
            ['label' => $key, 'nilai' => $nilai],
        ];
    }

    private function resolveSignaturePath(?string $stored): ?string
    {
        if (!is_string($stored) || $stored === '') {
            return null;
        }
        // If it's an R2/public URL or absolute URL, pass through.
        if (preg_match('#^https?://#i', $stored)) {
            return $stored;
        }
        // Treat as relative path under public/.
        $abs = public_path($stored);
        if (is_file($abs)) {
            return $abs;
        }
        return null;
    }

    private function placeholderUrl(string $role): ?string
    {
        $abs = public_path("signatures/{$role}.png");
        return is_file($abs) ? $abs : null;
    }
}
