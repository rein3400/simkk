<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatatanTreatment;
use App\Models\FotoKlinis;
use App\Models\Pasien;
use App\Models\Terapis;
use App\Models\User;
use App\Services\AuditService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RekamMedisController extends Controller
{
    public function addTreatment(Request $request, int $patient, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        $pasien = Pasien::findOrFail($patient);

        // F-007 fix: Terapis can only write patients assigned to them.
        // Manajer (also allowed by route middleware) bypasses this check.
        if ($user->level === 'Terapis') {
            $terapis = $this->resolveTerapisForUser($user);
            if ($terapis === null) {
                return response()->json([
                    'message' => 'Akun terapis belum terhubung ke data terapis. Hubungi admin.',
                ], 422);
            }
            if ($pasien->assigned_terapis_id !== $terapis->id) {
                return response()->json([
                    'message' => 'Pasien ini bukan pasien yang Anda tangani.',
                ], 403);
            }
        }

        $validated = $request->validate([
            'judul'   => 'required|string|max:100',
            'catatan' => 'required|string|max:5000',
            'terapis' => 'prohibited', // F-005: hard reject to surface client misuse; therapist name is server-derived.
        ]);

        // F-005 fix: therapist name is derived from the auth user, not the request body.
        // Eliminates impersonation: a Terapis cannot attribute their note to another therapist.
        $terapisName = $user->level === 'Terapis'
            ? $this->resolveTerapisForUser($user)->nama
            : ($validated['terapis'] ?? $user->nama_lengkap);

        $treatment = CatatanTreatment::create([
            'pasien_id' => $pasien->id,
            'tanggal'   => now()->format('d M'),
            'terapis'   => $terapisName,
            'judul'     => $validated['judul'],
            'catatan'   => $validated['catatan'],
        ]);

        $audit->log('treatment.create', $user, [
            'patient' => $pasien->rekam_medis_id,
            'treatment_id' => $treatment->id,
        ]);

        return response()->json([
            'id'        => $treatment->id,
            'date'      => $treatment->tanggal,
            'therapist' => $treatment->terapis,
            'title'     => $treatment->judul,
            'notes'     => $treatment->catatan,
        ], 201);
    }

    public function updateTreatment(Request $request, int $patient, int $treatment, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        $pasien = Pasien::findOrFail($patient);
        $treatment = CatatanTreatment::where('id', $treatment)
            ->where('pasien_id', $pasien->id)
            ->firstOrFail();

        // F-007: Terapis can only edit their own treatment notes
        if ($user->level === 'Terapis') {
            $terapis = $this->resolveTerapisForUser($user);
            if ($terapis === null || $treatment->terapis !== $terapis->nama) {
                return response()->json([
                    'message' => 'Anda tidak berhak mengubah catatan ini.',
                ], 403);
            }
        }

        $validated = $request->validate([
            'judul'   => 'required|string|max:100',
            'catatan' => 'required|string|max:5000',
        ]);

        $treatment->update($validated);

        $audit->log('treatment.update', $user, [
            'patient' => $pasien->rekam_medis_id,
            'treatment_id' => $treatment->id,
        ]);

        return response()->json([
            'id'        => $treatment->id,
            'date'      => $treatment->tanggal,
            'therapist' => $treatment->terapis,
            'title'     => $treatment->judul,
            'notes'     => $treatment->catatan,
        ]);
    }

    public function deleteTreatment(Request $request, int $patient, int $treatment, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        $pasien = Pasien::findOrFail($patient);
        $treatment = CatatanTreatment::where('id', $treatment)
            ->where('pasien_id', $pasien->id)
            ->firstOrFail();

        if ($user->level === 'Terapis') {
            $terapis = $this->resolveTerapisForUser($user);
            if ($terapis === null || $treatment->terapis !== $terapis->nama) {
                return response()->json([
                    'message' => 'Anda tidak berhak menghapus catatan ini.',
                ], 403);
            }
        }

        $treatment->delete();
        $audit->log('treatment.delete', $user, [
            'patient' => $pasien->rekam_medis_id,
            'treatment_id' => $treatment->id,
        ]);

        return response()->json(['deleted' => true]);
    }

    /**
     * Look up the Terapis row that corresponds to the auth user.
     * Match is by case-insensitive equality on nama (Terapis.nama) vs nama_lengkap (User.nama_lengkap).
     * Returns null when no match is found.
     */
    private function resolveTerapisForUser(User $user): ?Terapis
    {
        return Terapis::whereRaw('LOWER(nama) = ?', [strtolower($user->nama_lengkap)])->first();
    }

    public function addPhoto(Request $request, int $patient, StorageService $storage, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        $pasien = Pasien::findOrFail($patient);

        // F-007 fix: Terapis can only write patients assigned to them.
        // Manajer (also allowed by route middleware) bypasses this check.
        if ($user->level === 'Terapis') {
            $terapis = $this->resolveTerapisForUser($user);
            if ($terapis === null) {
                return response()->json([
                    'message' => 'Akun terapis belum terhubung ke data terapis. Hubungi admin.',
                ], 422);
            }
            if ($pasien->assigned_terapis_id !== $terapis->id) {
                return response()->json([
                    'message' => 'Pasien ini bukan pasien yang Anda tangani.',
                ], 403);
            }
        }

        $validated = $request->validate([
            'label'    => 'required|string|in:Before,After',
            'filename' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+\.(png|jpg|jpeg|webp|heic)$/i'],
            'content'  => 'required|string',
        ]);

        $bytes = $storage->decodeForValidation($validated['content']);
        if ($bytes === null || $bytes === '' || @getimagesizefromstring($bytes) === false) {
            return response()->json([
                'message' => 'The content is not a valid image.',
                'errors'  => ['content' => ['File content must be a valid PNG, JPEG, WebP, or HEIC image.']],
            ], 422);
        }

        $objectRef = $storage->storeClinicalPhoto(
            $pasien->rekam_medis_id,
            $validated['filename'],
            $validated['content'],
        );

        $photo = FotoKlinis::create([
            'pasien_id'  => $pasien->id,
            'label'      => $validated['label'],
            'tanggal'    => now()->format('d M'),
            'object_ref' => $objectRef,
        ]);

        $audit->log('photo.create', $user, [
            'patient' => $pasien->rekam_medis_id,
            'photo_id' => $photo->id,
        ]);

        return response()->json([
            'id'        => (string) $photo->id,
            'label'     => $photo->label,
            'date'      => $photo->tanggal,
            'objectRef' => $photo->object_ref,
            'url'       => url("/api/photos/{$photo->id}/raw"),
        ], 201);
    }

    public function deletePhoto(Request $request, int $patient, int $photo, StorageService $storage, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        $pasien = Pasien::findOrFail($patient);
        $foto = FotoKlinis::where('id', $photo)
            ->where('pasien_id', $pasien->id)
            ->firstOrFail();

        if ($user->level === 'Terapis') {
            $terapis = $this->resolveTerapisForUser($user);
            if ($terapis === null || $pasien->assigned_terapis_id !== $terapis->id) {
                return response()->json([
                    'message' => 'Pasien ini bukan pasien yang Anda tangani.',
                ], 403);
            }
        }

        $ref = $foto->object_ref;
        if (!str_starts_with($ref, 'local://')) {
            try {
                $storage::class; // hint
                \Storage::disk(config('sim-kk.storage.disk', 'local'))->delete($ref);
            } catch (\Throwable $e) {
                // best-effort cleanup; proceed with row deletion
            }
        }

        $foto->delete();
        $audit->log('photo.delete', $user, [
            'patient' => $pasien->rekam_medis_id,
            'photo_id' => $photo,
        ]);

        return response()->json(['deleted' => true]);
    }

    /**
     * Stream a clinical photo from R2 through Laravel.
     * Used as a proxy to avoid R2 presigned-URL signature quirks.
     */
    public function streamPhoto(Request $request, int $photo): StreamedResponse
    {
        $foto = FotoKlinis::findOrFail($photo);
        $disk = config('sim-kk.storage.disk', 'local');
        $ref  = $foto->object_ref;

        if (!str_starts_with($ref, 'local://') && \Storage::disk($disk)->exists($ref)) {
            $mime = \Storage::disk($disk)->mimeType($ref) ?: 'image/png';
            return \Storage::disk($disk)->response($ref, null, [
                'Content-Type' => $mime,
            ]);
        }

        // Fallback: serve a 1x1 transparent PNG so the browser doesn't show broken icon
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        return response()->stream(function () use ($png) { echo $png; }, 200, ['Content-Type' => 'image/png']);
    }
}
