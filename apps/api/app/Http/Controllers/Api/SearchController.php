<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Layanan;
use App\Models\Pasien;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * GET /api/search?q=...
     * Grouped cross-entity lookup, max 5 per group.
     * Role: Manajer only (per spec).
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json([
                'query'    => '',
                'patients' => [],
                'services' => [],
                'products' => [],
                'users'    => [],
            ]);
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        $patients = Pasien::query()
            ->where(function ($w) use ($like) {
                $w->where('nama_pasien', 'like', $like)
                  ->orWhere('rekam_medis_id', 'like', $like)
                  ->orWhere('nomor_telp', 'like', $like);
            })
            ->orderBy('nama_pasien')
            ->limit(5)
            ->get()
            ->map(fn (Pasien $p) => [
                'id'        => (int) $p->id,
                'recordId'  => $p->rekam_medis_id,
                'name'      => $p->nama_pasien,
                'phone'     => $p->nomor_telp,
            ]);

        $services = Layanan::query()
            ->where('nama', 'like', $like)
            ->orderBy('nama')
            ->limit(5)
            ->get()
            ->map(fn (Layanan $l) => [
                'id'       => (int) $l->id,
                'name'     => $l->nama,
                'category' => $l->kategori,
                'price'    => (int) $l->harga,
            ]);

        $products = Produk::query()
            ->where('nama', 'like', $like)
            ->orderBy('nama')
            ->limit(5)
            ->get()
            ->map(fn (Produk $p) => [
                'id'       => (int) $p->id,
                'name'     => $p->nama,
                'category' => $p->kategori,
            ]);

        $users = User::query()
            ->where(function ($w) use ($like) {
                $w->where('nama_lengkap', 'like', $like)
                  ->orWhere('username', 'like', $like);
            })
            ->orderBy('nama_lengkap')
            ->limit(5)
            ->get()
            ->map(fn (User $u) => [
                'id'          => (int) $u->id,
                'username'    => $u->username,
                'name'        => $u->nama_lengkap,
                'role'        => $u->level,
            ]);

        return response()->json([
            'query'    => $q,
            'patients' => $patients,
            'services' => $services,
            'products' => $products,
            'users'    => $users,
        ]);
    }
}
