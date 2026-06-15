<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Booking::with(['pasien:id,rekam_medis_id,nama_pasien', 'terapis:id,nama', 'layanan:id,nama']);
        if ($date = $request->query('date')) {
            $query->whereDate('scheduled_at', $date);
        }
        if ($terapisId = $request->query('terapis_id')) {
            $query->where('terapis_id', (int) $terapisId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        $bookings = $query->orderBy('scheduled_at')->limit(200)->get()->map(fn (Booking $b) => $this->present($b));
        return response()->json($bookings);
    }

    public function store(Request $request, AuditService $audit): JsonResponse
    {
        $validated = $request->validate([
            'pasien_id'    => 'required|integer|exists:pasien,id',
            'terapis_id'   => 'required|integer|exists:terapis,id',
            'layanan_id'   => 'nullable|integer|exists:layanan,id',
            'scheduled_at' => 'required|date',
            'duration_min' => 'nullable|integer|min:15|max:480',
            'notes'        => 'nullable|string|max:2000',
            'source'       => 'nullable|in:walk_in,phone,web',
        ]);

        $validated['duration_min'] = $validated['duration_min'] ?? 60;
        $validated['source'] = $validated['source'] ?? 'walk_in';
        $validated['created_by'] = $request->user()->id;

        $conflict = $this->findConflict(
            (int) $validated['terapis_id'],
            $validated['scheduled_at'],
            (int) $validated['duration_min'],
        );
        if ($conflict !== null) {
            return response()->json([
                'message' => 'Terapis sudah dibooking di slot tersebut.',
                'conflict' => $this->present($conflict),
            ], 409);
        }

        $booking = Booking::create($validated);
        $audit->log($request->user(), 'booking.create', 'booking', (string) $booking->id, null, $validated);

        return response()->json($this->present($booking->fresh(['pasien', 'terapis', 'layanan'])), 201);
    }

    public function update(Request $request, Booking $booking, AuditService $audit): JsonResponse
    {
        $validated = $request->validate([
            'pasien_id'    => 'sometimes|integer|exists:pasien,id',
            'terapis_id'   => 'sometimes|integer|exists:terapis,id',
            'layanan_id'   => 'nullable|integer|exists:layanan,id',
            'scheduled_at' => 'sometimes|date',
            'duration_min' => 'nullable|integer|min:15|max:480',
            'status'       => 'sometimes|in:booked,confirmed,done,cancelled,no_show',
            'notes'        => 'nullable|string|max:2000',
            'source'       => 'nullable|in:walk_in,phone,web',
        ]);

        $merged = array_merge($booking->toArray(), $validated);
        $conflict = $this->findConflict(
            (int) ($validated['terapis_id'] ?? $booking->terapis_id),
            $validated['scheduled_at'] ?? $booking->scheduled_at->toDateTimeString(),
            (int) ($validated['duration_min'] ?? $booking->duration_min),
            $booking->id,
        );
        if ($conflict !== null) {
            return response()->json([
                'message' => 'Terapis sudah dibooking di slot tersebut.',
                'conflict' => $this->present($conflict),
            ], 409);
        }

        $booking->update($validated);
        $audit->log($request->user(), 'booking.update', 'booking', (string) $booking->id, $booking->getOriginal(), $validated);

        return response()->json($this->present($booking->fresh(['pasien', 'terapis', 'layanan'])));
    }

    public function destroy(Request $request, Booking $booking, AuditService $audit): JsonResponse
    {
        $booking->delete();
        $audit->log($request->user(), 'booking.cancel', 'booking', (string) $booking->id, $booking->toArray(), null);
        return response()->json(['message' => 'Booking dibatalkan.']);
    }

    /**
     * Per revisi R2 — "next available slot" lookup. Returns both the
     * booked slots (so the UI can grey them out) and the free 30-min
     * increments between 09:00 and 21:00 on the requested date.
     */
    public function availability(Request $request): JsonResponse
    {
        $request->validate([
            'terapis_id' => 'required|integer|exists:terapis,id',
            'date'       => 'required|date_format:Y-m-d',
        ]);
        $terapisId = (int) $request->query('terapis_id');
        $date = $request->query('date');

        $booked = Booking::where('terapis_id', $terapisId)
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['booked', 'confirmed'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (Booking $b) => [
                'id'     => $b->id,
                'start'  => $b->scheduled_at->format('Y-m-d\TH:i:s'),
                'end'    => $b->endsAt()->format('Y-m-d\TH:i:s'),
                'pasien_id' => $b->pasien_id,
                'status' => $b->status,
            ])->values();

        $free = [];
        $cursor = CarbonImmutable::parse("$date 09:00");
        $endOfDay = CarbonImmutable::parse("$date 21:00");
        while ($cursor->lt($endOfDay)) {
            $slotEnd = $cursor->addMinutes(30);
            $hasConflict = $booked->contains(function ($b) use ($cursor, $slotEnd) {
                $bStart = CarbonImmutable::parse($b['start']);
                $bEnd = CarbonImmutable::parse($b['end']);
                return $cursor->lt($bEnd) && $slotEnd->gt($bStart);
            });
            $free[] = [
                'start' => $cursor->format('Y-m-d\TH:i:s'),
                'end'   => $slotEnd->format('Y-m-d\TH:i:s'),
                'available' => !$hasConflict,
            ];
            $cursor = $slotEnd;
        }

        return response()->json([
            'date'    => $date,
            'terapis_id' => $terapisId,
            'booked'  => $booked,
            'slots'   => $free,
        ]);
    }

    /**
     * Returns the first conflicting Booking (excluding $ignoreId if given)
     * for a given terapis + time range. Null if no conflict.
     */
    private function findConflict(int $terapisId, string $startsAt, int $durationMin, ?int $ignoreId = null): ?Booking
    {
        $start = CarbonImmutable::parse($startsAt);
        $end = $start->addMinutes($durationMin);
        return Booking::where('terapis_id', $terapisId)
            ->whereIn('status', ['booked', 'confirmed'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('scheduled_at', '<', $end)
            ->where(DB::raw("datetime(scheduled_at, '+' || duration_min || ' minutes')"), '>', $start)
            ->first();
    }

    private function present(Booking $b): array
    {
        return [
            'id'           => $b->id,
            'pasien'       => $b->pasien ? [
                'id' => $b->pasien->id,
                'rekam_medis_id' => $b->pasien->rekam_medis_id,
                'nama' => $b->pasien->nama_pasien,
            ] : null,
            'terapis'      => $b->terapis ? [
                'id' => $b->terapis->id,
                'nama' => $b->terapis->nama,
            ] : null,
            'layanan'      => $b->layanan?->nama,
            'scheduled_at' => $b->scheduled_at?->format('Y-m-d\TH:i:s'),
            'duration_min' => (int) $b->duration_min,
            'status'       => $b->status,
            'notes'        => $b->notes,
            'source'       => $b->source,
        ];
    }
}
