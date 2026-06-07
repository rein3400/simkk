<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Layanan;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LayananController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');

        $query = Layanan::query()->orderBy('kategori')->orderBy('nama');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nama', 'like', "%{$q}%")
                  ->orWhere('kategori', 'like', "%{$q}%");
            });
        }

        return response()->json($query->get()->map(fn (Layanan $l) => $this->serialize($l)));
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $data = $this->validatePayload($request);

        $layanan = Layanan::create($data);

        $audit->log($request->user(), 'layanan.create', 'layanan', (string) $layanan->id, null, $data);

        return response()->json($this->serialize($layanan), 201);
    }

    public function show(int $layanan): JsonResponse
    {
        $row = Layanan::findOrFail($layanan);
        return response()->json($this->serialize($row));
    }

    public function update(Request $request, int $layanan, AuditService $audit): JsonResponse
    {
        $row  = Layanan::findOrFail($layanan);
        $old  = $row->only(array_keys($this->baseRules($request, $row->id)));
        $data = $this->validatePayload($request, $row->id);

        $row->update($data);

        $audit->log($request->user(), 'layanan.update', 'layanan', (string) $row->id, $old, $data);

        return response()->json($this->serialize($row));
    }

    public function destroy(Request $request, int $layanan, AuditService $audit): JsonResponse
    {
        $row = Layanan::findOrFail($layanan);

        $used = \DB::table('transaksi_detail')
            ->where('id_produk', $row->id)
            ->count();
        if ($used > 0) {
            return response()->json([
                'message' => "Layanan '{$row->nama}' sudah dipakai di {$used} transaksi_detail. Tidak bisa dihapus; nonaktifkan atau ganti nama sebagai gantinya.",
            ], 422);
        }

        $row->delete();
        $audit->log($request->user(), 'layanan.delete', 'layanan', (string) $row->id, ['nama' => $row->nama], null);

        return response()->json(['deleted' => true]);
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate($this->baseRules($request, $ignoreId));
    }

    private function baseRules(Request $request, ?int $ignoreId = null): array
    {
        // Schema-level constraints (see migration 2026_06_01_000004):
        //   nama varchar(80), kategori varchar(20), durasi varchar(20) nullable,
        //   harga int, komisi_rate decimal(4,2), stok_produk_id FK nullable,
        //   dampak_stok varchar(30) nullable.
        // The spec asks for durasi (int minutes) and dampak_stok (int default 0);
        // the column is varchar, so we accept string and let MySQL truncate. We
        // normalize dampak_stok default to '0' on create.
        $rules = [
            'nama'           => ['required', 'string', 'max:80'],
            'kategori'       => ['required', 'string', 'max:20'],
            'durasi'         => ['nullable', 'string', 'max:20'],
            'harga'          => ['required', 'integer', 'min:0'],
            'komisi_rate'    => ['required', 'numeric', 'between:0,1'],
            'stok_produk_id' => ['nullable', 'integer', 'exists:produk,id'],
            'dampak_stok'    => ['nullable', 'string', 'max:30'],
        ];

        // All fields are optional on update.
        if ($request->isMethod('PATCH') || $request->isMethod('PUT') || $ignoreId !== null) {
            foreach ($rules as $k => $r) {
                $rules[$k] = array_merge(['sometimes'], $r);
            }
            // harga/komisi_rate required-with override.
            $rules['harga']       = ['sometimes', 'required', 'integer', 'min:0'];
            $rules['komisi_rate'] = ['sometimes', 'required', 'numeric', 'between:0,1'];
        }

        return $rules;
    }

    private function serialize(Layanan $l): array
    {
        return [
            'id'             => $l->id,
            'nama'           => $l->nama,
            'kategori'       => $l->kategori,
            'durasi'         => $l->durasi,
            'harga'          => (int) $l->harga,
            'komisi_rate'    => (float) $l->komisi_rate,
            'stok_produk_id' => $l->stok_produk_id,
            'dampak_stok'    => $l->dampak_stok ?? '0',
            'created_at'     => $l->created_at,
            'updated_at'     => $l->updated_at,
        ];
    }
}
