<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BatchStok;
use App\Models\DailyClosing;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard
     * Today stats for Manajer dashboard.
     *
     * All aggregates are derived from real tables:
     *   - transaksi (status='Lunas', created_at date filter)
     *   - buku_kas  (linked to transaksi)
     *   - daily_closing
     *   - batch_stok + produk (low-stock)
     *   - transaksi_detail + layanan (top services)
     *   - terapis (joined via transaksi.terapis_id for top therapists)
     */
    public function index(): JsonResponse
    {
        $today     = CarbonImmutable::today();
        $yesterday = $today->subDay();
        $todayEnd  = $today->endOfDay();
        $yEnd      = $yesterday->endOfDay();

        $threshold = (int) config('sim-kk.stock.menipis_threshold', 12);

        $revenueToday     = $this->sumPaidTotal($today, $todayEnd);
        $revenueYesterday = $this->sumPaidTotal($yesterday, $yEnd);

        $growth = 0.0;
        if ($revenueYesterday > 0) {
            $growth = round((($revenueToday - $revenueYesterday) / $revenueYesterday) * 100, 2);
        } elseif ($revenueToday > 0) {
            $growth = 100.0;
        }

        $transactionsToday = (int) Transaksi::query()
            ->where('status', 'Lunas')
            ->whereBetween('created_at', [$today, $todayEnd])
            ->count();

        $pendingClosings = (int) DailyClosing::query()
            ->whereDate('tanggal', $today->toDateString())
            ->where('status', DailyClosing::STATUS_SUBMITTED)
            ->count();

        // Low stock: products whose SUM(batches.qty) <= threshold.
        // Done in SQL to keep the math aligned with the seeded data.
        $lowStockCount = (int) DB::table('produk')
            ->leftJoin('batch_stok', 'batch_stok.produk_id', '=', 'produk.id')
            ->groupBy('produk.id')
            ->havingRaw('COALESCE(SUM(batch_stok.qty), 0) <= ?', [$threshold])
            ->get()
            ->count();

        // Top 3 therapists by jumlah tindakan for Lunas tx today.
        $topTherapists = DB::table('transaksi')
            ->join('terapis', 'terapis.id', '=', 'transaksi.terapis_id')
            ->where('transaksi.status', 'Lunas')
            ->whereBetween('transaksi.created_at', [$today, $todayEnd])
            ->groupBy('terapis.id', 'terapis.nama')
            ->selectRaw('terapis.nama as nama, COUNT(transaksi.id) as tindakan, COALESCE(SUM(transaksi.komisi_total), 0) as komisi')
            ->orderByDesc('tindakan')
            ->orderByDesc('komisi')
            ->limit(3)
            ->get()
            ->map(fn ($r) => [
                'nama'      => $r->nama,
                'tindakan'  => (int) $r->tindakan,
                'komisi'    => (int) $r->komisi,
            ]);

        // Top 3 services by jumlah transaksi_detail rows for Lunas tx today.
        $topServices = TransaksiDetail::query()
            ->join('transaksi', 'transaksi.id_transaksi', '=', 'transaksi_detail.id_transaksi')
            ->join('layanan', 'layanan.id', '=', 'transaksi_detail.id_produk')
            ->where('transaksi.status', 'Lunas')
            ->whereBetween('transaksi.created_at', [$today, $todayEnd])
            ->groupBy('layanan.id', 'layanan.nama')
            ->selectRaw('layanan.nama as nama, COUNT(transaksi_detail.id) as count')
            ->orderByDesc('count')
            ->limit(3)
            ->get()
            ->map(fn ($r) => [
                'nama'  => $r->nama,
                'count' => (int) $r->count,
            ]);

        // Last 7 days revenue (today inclusive, oldest first).
        $last7 = [];
        for ($i = 6; $i >= 0; $i--) {
            $day    = $today->subDays($i);
            $dayEnd = $day->endOfDay();
            $last7[] = [
                'date'  => $day->toDateString(),
                'total' => $this->sumPaidTotal($day, $dayEnd),
            ];
        }

        // Per revisi R1 — upcoming bookings (next 3 days).
        $upcomingBookings = DB::table('booking')
            ->join('pasien', 'pasien.id', '=', 'booking.pasien_id')
            ->join('terapis', 'terapis.id', '=', 'booking.terapis_id')
            ->whereIn('booking.status', ['booked', 'confirmed'])
            ->whereBetween('booking.scheduled_at', [$today, $today->addDays(3)->endOfDay()])
            ->orderBy('booking.scheduled_at')
            ->limit(10)
            ->select('booking.id', 'booking.scheduled_at', 'booking.duration_min', 'booking.status', 'pasien.nama_pasien as pasien_nama', 'pasien.rekam_medis_id', 'terapis.nama as terapis_nama')
            ->get()
            ->map(fn ($r) => [
                'id'           => (int) $r->id,
                'scheduled_at' => CarbonImmutable::parse($r->scheduled_at)->format('Y-m-d\TH:i:s'),
                'duration_min' => (int) $r->duration_min,
                'status'       => $r->status,
                'pasien'       => ['rekam_medis_id' => $r->rekam_medis_id, 'nama' => $r->pasien_nama],
                'terapis'      => ['nama' => $r->terapis_nama],
            ]);

        // Per revisi R1 — digest klien: 5 most recent patients with their
        // last treatment date and a summary of their most recent note.
        $digestKlien = DB::table('pasien')
            ->leftJoin('catatan_treatment', 'catatan_treatment.pasien_id', '=', 'pasien.id')
            ->whereNull('pasien.deleted_at')
            ->select(
                'pasien.id',
                'pasien.rekam_medis_id',
                'pasien.nama_pasien',
                DB::raw('MAX(catatan_treatment.tanggal) as last_treatment'),
                DB::raw('(SELECT catatan FROM catatan_treatment WHERE pasien_id = pasien.id ORDER BY tanggal DESC LIMIT 1) as last_note')
            )
            ->groupBy('pasien.id', 'pasien.rekam_medis_id', 'pasien.nama_pasien')
            ->orderByDesc('last_treatment')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id'             => (int) $r->id,
                'rekam_medis_id' => $r->rekam_medis_id,
                'nama'           => $r->nama_pasien,
                'last_treatment' => $r->last_treatment,
                'last_note'      => $r->last_note ? mb_substr((string) $r->last_note, 0, 80) : null,
            ]);

        return response()->json([
            'date'                  => $today->toDateString(),
            'revenue_today'         => $revenueToday,
            'revenue_yesterday'     => $revenueYesterday,
            'revenue_growth_pct'    => $growth,
            'transactions_today'    => $transactionsToday,
            'pending_closings'      => $pendingClosings,
            'low_stock_count'       => $lowStockCount,
            'top_therapists'        => $topTherapists,
            'top_services'          => $topServices,
            'last_7_days_revenue'   => $last7,
            'upcoming_bookings'     => $upcomingBookings,
            'digest_klien'          => $digestKlien,
        ]);
    }

    private function sumPaidTotal(CarbonImmutable $start, CarbonImmutable $end): int
    {
        return (int) Transaksi::query()
            ->where('status', 'Lunas')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total');
    }
}
