<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DailyReportService;
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
