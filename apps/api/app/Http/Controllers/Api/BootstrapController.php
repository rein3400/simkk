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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class BootstrapController extends Controller
{
    public function __construct(private readonly StorageService $storage) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->level;
        $isManajer = $role === 'Manajer';
        $isKasir = $role === 'Kasir';
        $isTerapis = $role === 'Terapis';
        $isGudang = $role === 'Gudang';

        // Role-scoped patient data (P1 #2 — privacy scoping).
        $pasien = $this->buildPatients($user, $role, $isManajer, $isKasir, $isTerapis);

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
            ->orderByDesc('id_transaksi');
        // Terapis sees only their own transactions; Gudang sees none.
        if ($isTerapis) {
            $terapisId = Terapis::whereRaw('LOWER(nama) = ?', [strtolower($user->nama_lengkap)])->value('id');
            if ($terapisId !== null) {
                $transaksi = $transaksi->where('terapis_id', $terapisId);
            } else {
                $transaksi = $transaksi->whereRaw('1 = 0');
            }
        } elseif ($isGudang) {
            $transaksi = $transaksi->whereRaw('1 = 0');
        }
        $transaksi = $transaksi
            ->get()
            ->map(fn ($t) => [
                'id'            => $t->id_transaksi,
                'patient'       => $t->pasien?->nama_pasien ?? '-',
                'therapist'     => $t->terapis?->nama ?? '-',
                'status'        => $t->status,
                'subtotal'      => (int) $t->subtotal,
                'discount'      => (int) $t->diskon,
                'paymentMethod' => $t->metode_bayar,
                'total'         => (int) $t->total,
                'commission'    => (int) $t->komisi_total,
                'time'          => $t->waktu,
            ]);

        $inventory = $isGudang || $isManajer ? $this->buildInventory() : [];

        $reports = $isManajer ? $this->buildReports() : [];

        // Users list: Manajer sees all; Terapis/Kasir/Gudang see only themselves.
        $users = User::orderBy('id');
        if (!$isManajer) {
            $users = $users->where('id', $user->id);
        }

        return response()->json([
            'users'        => $users->get()->map(fn ($u) => [
                'id'            => $u->id,
                'username'      => $u->username,
                'name'          => $u->nama_lengkap,
                'role'          => $u->level,
                'shift'         => $u->shift,
                'signaturePath' => $u->signature_path,
            ]),
            'patients'     => $pasien,
            'services'     => $isGudang ? [] : $layanan,
            'therapists'   => $isGudang ? [] : $terapis,
            'transactions' => $transaksi,
            'inventory'    => $inventory,
            'reports'      => $reports,
        ]);
    }

    /**
     * Role-scoped patient list (P1 #2 — privacy scoping).
     *
     * Manajer: full list with all treatments and photos.
     * Kasir:   name + recordId only, no treatments/photos (still needs the
     *          list for the POS cart patient picker).
     * Terapis: only patients assigned to them, with full treatments/photos
     *          so the medical-record view has data.
     * Gudang:  no patients (gudang has no patient-related workflow).
     */
    private function buildPatients(User $user, string $role, bool $isManajer, bool $isKasir, bool $isTerapis)
    {
        if ($role === 'Gudang') {
            return collect();
        }

        if ($isTerapis) {
            $terapis = Terapis::whereRaw('LOWER(nama) = ?', [strtolower($user->nama_lengkap)])->first();
            if ($terapis === null) {
                return collect();
            }
            $query = Pasien::with(['treatments', 'photos'])
                ->where('assigned_terapis_id', $terapis->id)
                ->orderBy('id');
        } else {
            $query = Pasien::with(['treatments', 'photos'])->orderBy('id');
        }

        return $query->get()->map(function ($p) use ($isKasir) {
            $base = [
                'id'              => $p->id,
                'name'            => $p->nama_pasien,
                'age'             => $p->usia,
                'phone'           => $p->nomor_telp,
                'telegramChatId'  => $p->telegram_chat_id,
                'recordId'        => $p->rekam_medis_id,
                'concern'         => $p->keluhan,
                'lastVisit'       => $p->last_visit,
                'riskNote'        => $p->risk_note,
            ];
            if ($isKasir) {
                return $base;
            }
            return $base + [
                'treatments' => $p->treatments->map(fn ($t) => [
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
                    'url'       => $this->photoUrl($f->id),
                ]),
                // Per revisi R8 — group treatments + photos by date for
                // collapsible session cards in the UI.
                'sessions' => $this->groupBySessionDate($p),
            ];
        });
    }

    /**
     * Group a patient's treatments and photos into collapsible session
     * cards by date. Each session has {date, treatments: [...], photos: [...],
     * note_excerpt: string|null} so the UI can render one card per visit.
     */
    private function groupBySessionDate(\App\Models\Pasien $p): array
    {
        $byDate = [];
        foreach ($p->treatments as $t) {
            $date = (string) $t->tanggal;
            if (!isset($byDate[$date])) $byDate[$date] = ['date' => $date, 'treatments' => [], 'photos' => [], 'note_excerpt' => null];
            $byDate[$date]['treatments'][] = [
                'id'        => $t->id,
                'therapist' => $t->terapis,
                'title'     => $t->judul,
                'notes'     => $t->catatan,
            ];
            $excerpt = $t->catatan ? mb_substr((string) $t->catatan, 0, 80) : null;
            if ($excerpt) $byDate[$date]['note_excerpt'] = $excerpt;
        }
        foreach ($p->photos as $f) {
            $date = (string) $f->tanggal;
            if (!isset($byDate[$date])) $byDate[$date] = ['date' => $date, 'treatments' => [], 'photos' => [], 'note_excerpt' => null];
            $byDate[$date]['photos'][] = [
                'id'        => (string) $f->id,
                'label'     => $f->label,
                'objectRef' => $f->object_ref,
                'url'       => $this->photoUrl($f->id),
            ];
        }
        // Newest first.
        usort($byDate, fn ($a, $b) => strcmp($b['date'], $a['date']));
        return array_values($byDate);
    }

    private function photoUrl(int $photoId): string
    {
        return URL::temporarySignedRoute('photos.raw', now()->addHours(12), ['photo' => $photoId]);
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
                    'id'      => (int) $b->id,
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
