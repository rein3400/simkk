<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Supplier::orderBy('nama')->get()->map(fn (Supplier $s) => [
                'id'      => $s->id,
                'nama'    => $s->nama,
                'kontak'  => $s->kontak,
                'telepon' => $s->telepon,
                'email'   => $s->email,
            ])->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama'    => 'required|string|max:80|unique:supplier,nama',
            'kontak'  => 'nullable|string|max:80',
            'telepon' => 'nullable|string|max:30',
            'email'   => 'nullable|email|max:100',
            'alamat'  => 'nullable|string',
            'catatan' => 'nullable|string',
        ]);

        $supplier = Supplier::create($validated);
        return response()->json($this->present($supplier), 201);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'nama'    => ['required', 'string', 'max:80', Rule::unique('supplier', 'nama')->ignore($supplier->id)],
            'kontak'  => 'nullable|string|max:80',
            'telepon' => 'nullable|string|max:30',
            'email'   => 'nullable|email|max:100',
            'alamat'  => 'nullable|string',
            'catatan' => 'nullable|string',
        ]);

        $supplier->update($validated);
        return response()->json($this->present($supplier));
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();
        return response()->json(['message' => 'Supplier dihapus.'], 200);
    }

    private function present(Supplier $s): array
    {
        return [
            'id'      => $s->id,
            'nama'    => $s->nama,
            'kontak'  => $s->kontak,
            'telepon' => $s->telepon,
            'email'   => $s->email,
            'alamat'  => $s->alamat,
            'catatan' => $s->catatan,
        ];
    }
}
