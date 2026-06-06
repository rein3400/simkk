<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryMovementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function __construct(
        private readonly InventoryMovementService $service,
    ) {
    }

    /**
     * GET /api/inventory-movements?from=YYYY-MM-DD&to=YYYY-MM-DD
     * Role: Gudang + Manajer (enforced at route level)
     */
    public function index(Request $request): JsonResponse
    {
        $from = (string) $request->query('from', CarbonImmutable::now()->startOfMonth()->toDateString());
        $to   = (string) $request->query('to',   CarbonImmutable::now()->endOfMonth()->toDateString());

        $this->assertValidDate($from, 'from');
        $this->assertValidDate($to,   'to');

        $rows = $this->service->query($from, $to);

        return response()->json([
            'from'   => $from,
            'to'     => $to,
            'count'  => $rows->count(),
            'rows'   => $rows,
        ], 200);
    }

    private function assertValidDate(string $value, string $field): void
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            abort(422, "Format tanggal untuk '{$field}' harus YYYY-MM-DD.");
        }
    }
}
