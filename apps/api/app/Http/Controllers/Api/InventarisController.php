<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventarisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventarisController extends Controller
{
    public function addPurchase(Request $request, InventarisService $service): JsonResponse
    {
        $validated = $request->validate([
            'produk_id'   => 'required|integer|exists:produk,id',
            'supplier'    => 'required|string|max:60',
            'kode_batch'  => 'required|string|max:30',
            'qty'         => 'required|integer|min:1',
            'hpp'         => 'required|integer|min:0',
            'kadaluarsa'  => 'nullable|date|after:today',
        ]);

        $result = $service->addPurchase($validated);
        return response()->json($result, 201);
    }
}
