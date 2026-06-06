<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CatatanTreatment;
use App\Models\FotoKlinis;
use App\Models\Pasien;
use App\Models\Terapis;
use App\Models\User;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RekamMedisController extends Controller
{
    public function addTreatment(Request $request, int $patient): JsonResponse
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

        return response()->json([
            'id'        => $treatment->id,
            'date'      => $treatment->tanggal,
            'therapist' => $treatment->terapis,
            'title'     => $treatment->judul,
            'notes'     => $treatment->catatan,
        ], 201);
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

    public function addPhoto(Request $request, int $patient, StorageService $storage): JsonResponse
    {
        $pasien = Pasien::findOrFail($patient);

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

        return response()->json([
            'id'        => (string) $photo->id,
            'label'     => $photo->label,
            'date'      => $photo->tanggal,
            'objectRef' => $photo->object_ref,
        ], 201);
    }
}
