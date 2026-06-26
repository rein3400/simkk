<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyClosing;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\DailyReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DailyReportController extends Controller
{
    public function __construct(
        private readonly DailyReportService $service,
    ) {
    }

    /**
     * GET /api/daily-reports/status?tanggal=YYYY-MM-DD
     * Returns closing status for the given date.
     * Status resolution:
     *   - 'approved'  : DailyClosing.status = 'approved'
     *   - 'submitted' : DailyClosing.status = 'submitted'
     *   - 'pending'   : no DailyClosing row but Lunas transactions exist that day
     *   - null        : no Lunas transactions that day
     * Any authenticated user (Manajer/Kasir/Terapis) can read.
     */
    public function status(Request $request): JsonResponse
    {
        $tanggal = (string) $request->query('tanggal', '');
        $parsed = \DateTime::createFromFormat('Y-m-d', $tanggal);
        if (!$parsed || $parsed->format('Y-m-d') !== $tanggal) {
            return response()->json([
                'message' => 'Format tanggal harus YYYY-MM-DD.',
            ], 422);
        }

        // Per revisi R12 — nilai komisi hanya untuk Manajer. Role lain
        // (Kasir/Terapis/Gudang) tidak boleh lihat angka komisi.
        $isManajer = $request->user() && $request->user()->level === 'Manajer';

        $date    = CarbonImmutable::parse($tanggal)->startOfDay();
        $dateEnd = $date->endOfDay();

        $closing = DailyClosing::with(['kasir:id,username,nama_lengkap', 'manajer:id,username,nama_lengkap'])
            ->whereDate('tanggal', $date->toDateString())
            ->first();

        $lunasCount = (int) Transaksi::query()
            ->where('status', 'Lunas')
            ->whereBetween('created_at', [$date, $dateEnd])
            ->count();

        $lunasTotal = (int) Transaksi::query()
            ->where('status', 'Lunas')
            ->whereBetween('created_at', [$date, $dateEnd])
            ->sum('total');

        $status = null;
        if ($closing && $closing->status === DailyClosing::STATUS_APPROVED) {
            $status = 'approved';
        } elseif ($closing && $closing->status === DailyClosing::STATUS_SUBMITTED) {
            $status = 'submitted';
        } elseif ($lunasCount > 0) {
            $status = 'pending';
        }

        return response()->json([
            'tanggal'           => $date->toDateString(),
            'status'            => $status,
            'closing_id'        => $closing?->id,
            'submitted_at'      => optional($closing?->submitted_at)?->toIso8601String(),
            'submitted_by'      => $closing?->kasir?->nama_lengkap,
            'approved_at'       => optional($closing?->approved_at)?->toIso8601String(),
            'approved_by'       => $closing?->manajer?->nama_lengkap,
            'total_penjualan'   => (int) ($closing?->total_penjualan ?? $lunasTotal),
            // Per revisi R12 — komisi hanya di-expose ke Manajer.
            'total_komisi'      => $isManajer ? (int) Transaksi::query()
                ->where('status', 'Lunas')
                ->whereBetween('created_at', [$date, $dateEnd])
                ->sum('komisi_total') : null,
            'transaction_count' => $lunasCount,
        ]);
    }

    /**
     * GET /api/daily-reports/{tanggal}/export
     * Role: Manajer
     */
    public function export(string $tanggal): Response
    {
        $this->assertValidDate($tanggal);

        $pdf = $this->service->generate($tanggal);
        $filename = 'daily-report-' . $tanggal . '.pdf';

        return response($pdf)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * POST /api/daily-reports/{tanggal}/submit
     * Role: Kasir + Manajer
     */
    public function submit(Request $request, string $tanggal): JsonResponse
    {
        $this->assertValidDate($tanggal);
        $user = $request->user();
        abort_if(!$user, 401, 'Sesi tidak valid.');

        $closing = $this->service->submit($tanggal, (int) $user->id);

        return response()->json([
            'message' => 'Daily report submitted for approval.',
            'closing' => $this->serialize($closing),
        ], 200);
    }

    /**
     * POST /api/daily-reports/closings/{id}/approve
     * Role: Manajer only (enforced at route level)
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_if(!$user, 401, 'Sesi tidak valid.');

        $closing = $this->service->approve($id, (int) $user->id);

        return response()->json([
            'message' => 'Daily report approved.',
            'closing' => $this->serialize($closing),
        ], 200);
    }

    private function assertValidDate(string $tanggal): void
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $tanggal);
        if (!$parsed || $parsed->format('Y-m-d') !== $tanggal) {
            abort(422, 'Format tanggal harus YYYY-MM-DD.');
        }
    }

    private function serialize($closing): array
    {
        return [
            'id'                  => (int) $closing->id,
            'tanggal'             => optional($closing->tanggal)->toDateString(),
            'status'              => $closing->status,
            'user_kasir_id'       => $closing->user_kasir_id,
            'user_manajer_id'     => $closing->user_manajer_id,
            'submitted_at'        => optional($closing->submitted_at)?->toIso8601String(),
            'approved_at'         => optional($closing->approved_at)?->toIso8601String(),
            'total_penjualan'     => (int) $closing->total_penjualan,
            'total_card'          => (int) $closing->total_card,
            'total_tunai'         => (int) $closing->total_tunai,
            'pnl'                 => (int) $closing->pnl,
            'setoran_bank'        => (int) $closing->setoran_bank,
        ];
    }
}
