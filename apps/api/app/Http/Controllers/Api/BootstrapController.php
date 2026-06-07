<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BatchStok;
use App\Models\BukuKas;
use App\Models\Layanan;
use App\Models\Pasien;
use App\Models\Terapis;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;

class BootstrapController extends Controller
{
    public function __construct(private readonly StorageService $storage) {}

    public function index(): JsonResponse
    {
        $pasien = Pasien::with(['treatments', 'photos'])
            ->orderBy('id')
            ->get()
            ->map(fn ($p) => [
                'id'              => $p->id,
                'name'            => $p->nama_pasien,
                'age'             => $p->usia,
                'phone'           => $p->nomor_telp,
                'telegramChatId'  => $p->telegram_chat_id,
                'recordId'        => $p->rekam_medis_id,
                'concern'         => $p->keluhan,
                'lastVisit'       => $p->last_visit,
                'riskNote'        => $p->risk_note,
                'treatments'  => $p->treatments->map(fn ($t) => [
                    'id'        => $t->id,
                    'date'      => $t->tanggal,
                    'therapist' => $t->terapis,
                    'title'     => $t->judul,
                    'notes'     => $t->catatan,
                ]),
                'photos' => $p->photos->map(fn ($f) => [
                    'id'        => (string) $f->id,
                    'label'     => $f->label,
                    'date'      => $f->tanggal,
                    'objectRef' => $f->object_ref,
                    'url'       => url("/api/photos/{$f->id}/raw"),
                ]),
            ]);

        $layanan = Layanan::orderBy('id')->get()->map(fn ($l) => [
            'id'             => $l->id,
            'name'           => $l->nama,
            'category'       => $l->kategori,
            'duration'       => $l->durasi,
            'price'          => (int) $l->harga,
            'commissionRate' => (float) $l->komisi_rate,
            'stockProductId' => $l->stok_produk_id,
            'stockImpact'    => $l->dampak_stok,
        ]);

        $terapis = Terapis::orderBy('id')->get()->map(fn ($t) => [
            'id'          => $t->id,
            'name'        => $t->nama,
            'specialty'   => $t->spesialisasi,
            'status'      => $t->status,
        ]);

        $transaksi = Transaksi::with(['pasien', 'terapis'])
            ->orderByDesc('created_at')
            ->orderByDesc('id_transaksi')
            ->get()
            ->map(fn ($t) => [
                'id'            => $t->id_transaksi,
                'patient'       => $t->pasien->nama_pasien,
                'therapist'     => $t->terapis?->nama ?? '-',
                'status'        => $t->status,
                'subtotal'      => (int) $t->subtotal,
                'discount'      => (int) $t->diskon,
                'paymentMethod' => $t->metode_bayar,
                'total'         => (int) $t->total,
                'commission'    => (int) $t->komisi_total,
                'time'          => $t->waktu,
            ]);

        $inventory = $this->buildInventory();

        $reports = $this->buildReports();

        return response()->json([
            'users'        => User::orderBy('id')->get()->map(fn ($u) => [
                'id'            => $u->id,
                'username'      => $u->username,
                'name'          => $u->nama_lengkap,
                'role'          => $u->level,
                'shift'         => $u->shift,
                'signaturePath' => $u->signature_path,
            ]),
            'patients'     => $pasien,
            'services'     => $layanan,
            'therapists'   => $terapis,
            'transactions' => $transaksi,
            'inventory'    => $inventory,
            'reports'      => $reports,
        ]);
    }

    private function buildInventory(): array
    {
        return \App\Models\Produk::with(['batches' => function ($q) {
            $q->where('qty', '>', 0)
              ->orderByRaw("CASE WHEN kadaluarsa IS NULL THEN '9999-12-31' ELSE kadaluarsa END ASC")
              ->orderBy('id');
        }])
        ->orderBy('id')
        ->get()
        ->map(function ($p) {
            $batches = $p->batches;
            $total = $batches->sum('qty');
            $firstExpiry = $batches->first()?->kadaluarsa?->format('Y-m-d') ?? '9999-12-31';
            $status = 'Aman';
            if ($firstExpiry !== '9999-12-31' && $firstExpiry <= config('sim-kk.stock.prioritas_expiry', '2026-07-31')) {
                $status = 'Prioritas';
            } elseif ($total <= config('sim-kk.stock.menipis_threshold', 12)) {
                $status = 'Menipis';
            }
            return [
                'id'          => $p->id,
                'name'        => $p->nama,
                'category'    => $p->kategori,
                'totalStock'  => (int) $total,
                'status'      => $status,
                'batches'     => $batches->values()->map(fn ($b, $i) => [
                    'code'    => $b->kode_batch,
                    'qty'     => (int) $b->qty,
                    'hpp'     => (int) $b->hpp,
                    'expiry'  => $b->kadaluarsa?->format('Y-m-d') ?? 'Reusable',
                    'supplier'=> $b->supplier,
                    'firstOut' => $i === 0,
                ]),
            ];
        })
        ->toArray();
    }

    private function buildReports(): array
    {
        $ledger = BukuKas::orderBy('id')->get();
        $saldo = 0;
        $financeRows = $ledger->map(function ($r) use (&$saldo) {
            $debit  = $r->tipe === 'Debit' ? (int) $r->jumlah : 0;
            $kredit = $r->tipe === 'Kredit' ? (int) $r->jumlah : 0;
            $saldo += $debit - $kredit;
            return [
                'id'    => $r->id_transaksi,
                'debit' => rupiah($debit),
                'kredit'=> rupiah($kredit),
                'saldo' => rupiah($saldo),
            ];
        });

        $stockRows = \App\Models\Produk::with(['batches' => function ($q) {
            $q->where('qty', '>', 0)->orderByRaw("CASE WHEN kadaluarsa IS NULL THEN '9999-12-31' ELSE kadaluarsa END ASC")->orderBy('id');
        }])->orderBy('id')->get()->map(function ($p) {
            $batch = $p->batches->first();
            return [
                'produk' => $p->nama,
                'stok'   => (int) $p->batches->sum('qty'),
                'batch'  => $batch?->kode_batch ?? '-',
                'hpp'    => rupiah($batch?->hpp ?? 0),
            ];
        });

        $komisiRows = Terapis::leftJoin('transaksi', function ($j) {
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
            'idPegawai'  => 'TRP-' . str_pad($r->id, 3, '0', STR_PAD_LEFT),
            'pegawai'    => $r->nama,
            'tindakan'   => (int) $r->tindakan,
            'komisi'     => rupiah((int) $r->komisi),
            'gajiPokok'  => rupiah((int) $r->gaji_pokok),
            'grandTotal' => rupiah((int) $r->gaji_pokok + (int) $r->komisi),
        ]);

        return [
            ['id' => 'finance',    'title' => 'Laporan Arus Kas',   'output' => 'PDF',  'period' => now()->format('M Y'), 'rows' => $financeRows->toArray()],
            ['id' => 'stock',      'title' => 'Laporan Stok FIFO',  'output' => 'XLSX', 'period' => now()->format('M Y'), 'rows' => $stockRows->toArray()],
            ['id' => 'commission', 'title' => 'Komisi Terapis',     'output' => 'XLSX', 'period' => now()->format('M Y'), 'rows' => $komisiRows->toArray()],
        ];
    }
}

function rupiah(int $value): string
{
    return 'Rp' . number_format($value, 0, ',', '.');
}
