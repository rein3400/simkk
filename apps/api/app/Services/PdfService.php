<?php

namespace App\Services;

use App\Models\BukuKas;
use App\Models\TransaksiDetail;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

class PdfService
{
    public function generateFinanceReport(): string
    {
        $ledger = BukuKas::orderBy('id')->get();
        $saldo = 0;
        $rows = $ledger->map(function ($r) use (&$saldo) {
            $debit  = $r->tipe === 'Debit' ? (int) $r->jumlah : 0;
            $kredit = $r->tipe === 'Kredit' ? (int) $r->jumlah : 0;
            $saldo += $debit - $kredit;
            return [
                'id'    => $r->id_transaksi,
                'debit' => $debit,
                'kredit'=> $kredit,
                'saldo' => $saldo,
            ];
        });

        $totalDebit = $ledger->where('tipe', 'Debit')->sum('jumlah');
        $totalHpp = (int) TransaksiDetail::join('transaksi', 'transaksi.id_transaksi', '=', 'transaksi_detail.id_transaksi')
            ->where('transaksi.status', 'Lunas')
            ->sum(\DB::raw('transaksi_detail.harga_satuan * transaksi_detail.qty * 0.5'));
        $labaKotor = (int) $totalDebit - $totalHpp;

        $kopName    = config('sim-kk.clinic.name', 'KLINIK KECANTIKAN SIM-KK');
        $kopAddress = config('sim-kk.clinic.address', 'Jl. Operasional Klinik No. 25, Samarinda');
        $period     = now()->format('F Y');

        $html = View::make('reports.finance-pdf', [
            'kopName'    => $kopName,
            'kopAddress' => $kopAddress,
            'period'     => $period,
            'rows'       => $rows,
            'totalDebit' => $totalDebit,
            'totalHpp'   => $totalHpp,
            'labaKotor'  => $labaKotor,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html);
        $pdf->render();
        return $pdf->output();
    }
}
