<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProdukController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');

        $query = Produk::query()->orderBy('kategori')->orderBy('nama');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nama', 'like', "%{$q}%")
                  ->orWhere('kategori', 'like', "%{$q}%");
            });
        }

        $rows = $query->get()->map(function (Produk $p) {
            $total = (int) $p->batches()->where('qty', '>', 0)->sum('qty');
            $firstExpiry = optional($p->batches()->where('qty', '>', 0)->orderByRaw("CASE WHEN kadaluarsa IS NULL THEN '9999-12-31' ELSE kadaluarsa END ASC")->first())->kadaluarsa?->format('Y-m-d') ?? '9999-12-31';
            $status = 'Aman';
            if ($firstExpiry !== '9999-12-31' && $firstExpiry <= config('sim-kk.stock.prioritas_expiry', '2026-07-31')) {
                $status = 'Prioritas';
            } elseif ($total <= config('sim-kk.stock.menipis_threshold', 12)) {
                $status = 'Menipis';
            }
            return $this->serialize($p, $total, $status);
        });

        return response()->json($rows);
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $data = $this->validatePayload($request);

        $produk = Produk::create($data);

        $audit->log($request->user(), 'produk.create', 'produk', (string) $produk->id, null, $data);

        return response()->json($this->serialize($produk), 201);
    }

    public function show(int $produk): JsonResponse
    {
        $row = Produk::findOrFail($produk);
        return response()->json($this->serialize($row));
    }

    public function update(Request $request, int $produk, AuditService $audit): JsonResponse
    {
        $row  = Produk::findOrFail($produk);
        $old  = $row->only(['nama', 'kategori']);
        $data = $this->validatePayload($request, $row->id);

        $row->update($data);

        $audit->log($request->user(), 'produk.update', 'produk', (string) $row->id, $old, $data);

        return response()->json($this->serialize($row));
    }

    public function destroy(Request $request, int $produk, AuditService $audit): JsonResponse
    {
        $row = Produk::findOrFail($produk);

        $batchCount = DB::table('batch_stok')->where('produk_id', $row->id)->count();
        $detailCount = DB::table('transaksi_detail')
            ->whereIn('id_produk', function ($q) use ($row) {
                $q->select('id')->from('layanan')->where('stok_produk_id', $row->id);
            })
            ->count();

        if ($batchCount > 0 || $detailCount > 0) {
            $parts = [];
            if ($batchCount > 0) {
                $parts[] = "{$batchCount} batch_stok";
            }
            if ($detailCount > 0) {
                $parts[] = "{$detailCount} transaksi_detail (via layanan)";
            }
            return response()->json([
                'message' => "Produk '{$row->nama}' masih dirujuk oleh " . implode(' dan ', $parts) . ". Tidak bisa dihapus.",
            ], 422);
        }

        $row->delete();
        $audit->log($request->user(), 'produk.delete', 'produk', (string) $row->id, ['nama' => $row->nama], null);

        return response()->json(['deleted' => true]);
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'nama'     => ['required', 'string', 'max:80'],
            'kategori' => ['required', 'string', 'max:20'],
        ];

        if ($request->isMethod('PATCH') || $request->isMethod('PUT') || $ignoreId !== null) {
            $rules['nama']     = ['sometimes', 'required', 'string', 'max:80'];
            $rules['kategori'] = ['sometimes', 'required', 'string', 'max:20'];
        }

        return $request->validate($rules);
    }

    private function serialize(Produk $p, ?int $totalStok = null, ?string $status = null): array
    {
        return [
            'id'         => $p->id,
            'nama'       => $p->nama,
            'kategori'   => $p->kategori,
            'total_stok' => $totalStok ?? (int) $p->batches()->where('qty', '>', 0)->sum('qty'),
            'status'     => $status ?? 'Aman',
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
        ];
    }
}
