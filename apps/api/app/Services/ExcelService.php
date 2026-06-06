<?php

namespace App\Services;

use App\Models\Produk;
use App\Models\Terapis;
use App\Models\BatchStok;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockReportExport;
use App\Exports\CommissionReportExport;

class ExcelService
{
    public function generate(string $reportId): string
    {
        if ($reportId === 'stock') {
            return Excel::raw(new StockReportExport($this->stockRows()), \Maatwebsite\Excel\Excel::XLSX);
        }
        if ($reportId === 'commission') {
            return Excel::raw(new CommissionReportExport($this->commissionRows()), \Maatwebsite\Excel\Excel::XLSX);
        }
        abort(404, 'Report tidak ditemukan.');
    }

    private function stockRows(): array
    {
        return Produk::orderBy('id')->get()->map(function ($p) {
            $batch = BatchStok::where('produk_id', $p->id)
                ->where('qty', '>', 0)
                ->orderByRaw("CASE WHEN kadaluarsa IS NULL THEN '9999-12-31' ELSE kadaluarsa END ASC")
                ->orderBy('id')
                ->first();
            return [
                'Produk' => $p->nama,
                'Stok'   => (int) BatchStok::where('produk_id', $p->id)->sum('qty'),
                'Batch'  => $batch?->kode_batch ?? '-',
                'HPP'    => (int) ($batch?->hpp ?? 0),
            ];
        })->toArray();
    }

    private function commissionRows(): array
    {
        return Terapis::leftJoin('transaksi', function ($j) {
            $j->on('terapis.id', '=', 'transaksi.terapis_id')
              ->where('transaksi.status', '=', 'Lunas');
        })
        ->select(
            'terapis.id',
            'terapis.nama',
            \DB::raw('COUNT(transaksi.id_transaksi) as tindakan'),
            \DB::raw('COALESCE(SUM(transaksi.komisi_total), 0) as komisi'),
            'terapis.gaji_pokok',
        )
        ->groupBy('terapis.id', 'terapis.nama', 'terapis.gaji_pokok')
        ->orderBy('terapis.id')
        ->get()
        ->map(fn ($r) => [
            'ID Pegawai'  => 'TRP-' . str_pad($r->id, 3, '0', STR_PAD_LEFT),
            'Nama Terapis'=> $r->nama,
            'Jumlah Tindakan' => (int) $r->tindakan,
            'Total Komisi'    => (int) $r->komisi,
            'Gaji Pokok'      => (int) $r->gaji_pokok,
            'Grand Total'     => (int) $r->gaji_pokok + (int) $r->komisi,
        ])->toArray();
    }
}
