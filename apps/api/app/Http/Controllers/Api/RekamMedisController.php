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
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

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
            'tanggal'   => now()->format('Y-m-d'),
            'terapis'   => $terapisName,
            'judul'     => $validated['judul'],
            'catatan'   => $validated['catatan'],
        ]);

        $audit->log($user, 'treatment.create', 'catatan_treatment', (string) $treatment->id, null, [
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

        $before = $treatment->only(['judul', 'catatan']);
        $treatment->update($validated);

        $audit->log($user, 'treatment.update', 'catatan_treatment', (string) $treatment->id, $before, [
            'patient' => $pasien->rekam_medis_id,
            'treatment_id' => $treatment->id,
            'judul' => $treatment->judul,
            'catatan' => $treatment->catatan,
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

        $before = $treatment->only(['pasien_id', 'tanggal', 'terapis', 'judul', 'catatan']);
        $treatment->delete();
        $audit->log($user, 'treatment.delete', 'catatan_treatment', (string) $treatment->id, $before, [
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
            'content'  => 'required|string|max:14336',
        ]);

        try {
            $photo = $this->processSinglePhoto($pasien, $validated, $storage, $audit, $user);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'The content is not a valid image.',
                'errors'  => ['content' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'id'        => (string) $photo->id,
            'label'     => $photo->label,
            'date'      => $photo->tanggal,
            'objectRef' => $photo->object_ref,
            'url'       => $this->photoUrl($photo->id),
        ], 201);
    }

    /**
     * Per revisi "dibuat bisa lebih dari 1 gambar" — batch photo upload.
     * Max 10 photos per request. Each photo validated individually; failures
     * do not abort the whole batch — they are reported in the `errors` array
     * with their original index. Successfully uploaded photos go to `uploaded`.
     */
    public function addPhotos(Request $request, int $patient, StorageService $storage, AuditService $audit): JsonResponse
    {
        $user = $request->user();
        $pasien = Pasien::findOrFail($patient);

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
            'photos'              => 'required|array|min:1|max:10',
            'photos.*.label'      => 'required|string|in:Before,After',
            'photos.*.filename'   => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+\.(png|jpg|jpeg|webp|heic)$/i'],
            'photos.*.content'    => 'required|string|max:14336',
        ]);

        $uploaded = [];
        $errors = [];
        foreach ($validated['photos'] as $index => $photoData) {
            try {
                $photo = $this->processSinglePhoto($pasien, $photoData, $storage, $audit, $user);
                $uploaded[] = [
                    'id'        => (string) $photo->id,
                    'label'     => $photo->label,
                    'date'      => $photo->tanggal,
                    'objectRef' => $photo->object_ref,
                    'url'       => $this->photoUrl($photo->id),
                ];
            } catch (\InvalidArgumentException $e) {
                $errors[] = [
                    'index'   => $index,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'uploaded' => $uploaded,
            'errors'   => $errors,
        ], empty($errors) ? 201 : 207);
    }

    /**
     * Single photo ingestion — used by both addPhoto (legacy) and addPhotos
     * (batch). Encapsulates bytes validation, storage write, DB insert,
     * audit log.
     *
     * @param array{label:string,filename:string,content:string} $data
     */
    private function processSinglePhoto(Pasien $pasien, array $data, StorageService $storage, AuditService $audit, User $user): FotoKlinis
    {
        $bytes = $storage->decodeForValidation($data['content']);
        if ($bytes === null || $bytes === '' || @getimagesizefromstring($bytes) === false) {
            throw new \InvalidArgumentException('File content must be a valid PNG, JPEG, WebP, or HEIC image.');
        }

        $objectRef = $storage->storeClinicalPhoto(
            $pasien->rekam_medis_id,
            $data['filename'],
            $data['content'],
        );

        $photo = FotoKlinis::create([
            'pasien_id'  => $pasien->id,
            'label'      => $data['label'],
            'tanggal'    => now()->format('Y-m-d'),
            'object_ref' => $objectRef,
        ]);

        $audit->log($user, 'photo.create', 'foto_klinis', (string) $photo->id, null, [
            'patient' => $pasien->rekam_medis_id,
            'photo_id' => $photo->id,
        ]);

        return $photo;
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
                Storage::disk($this->storageDisk())->delete($ref);
            } catch (\Throwable $e) {
                // best-effort cleanup; proceed with row deletion
            }
        }

        $before = $foto->only(['pasien_id', 'label', 'tanggal', 'object_ref']);
        $foto->delete();
        $audit->log($user, 'photo.delete', 'foto_klinis', (string) $photo, $before, [
            'patient' => $pasien->rekam_medis_id,
            'photo_id' => $photo,
        ]);

        return response()->json(['deleted' => true]);
    }

    /**
     * Stream a clinical photo from R2 through Laravel.
     * Used as a proxy to avoid R2 presigned-URL signature quirks.
     */
    public function streamPhoto(Request $request, int $photo): Response
    {
        $foto = FotoKlinis::findOrFail($photo);
        $disk = $this->storageDisk();
        $ref  = $foto->object_ref;

        if (!str_starts_with($ref, 'local://') && Storage::disk($disk)->exists($ref)) {
            $mime = Storage::disk($disk)->mimeType($ref) ?: 'image/png';
            return Storage::disk($disk)->response($ref, null, [
                'Content-Type' => $mime,
            ]);
        }

        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="420" viewBox="0 0 640 420" role="img" aria-label="Foto tidak tersedia">
  <rect width="640" height="420" fill="#efe9dc"/>
  <rect x="24" y="24" width="592" height="372" rx="28" fill="#f5f1ea" stroke="#d8d3c5" stroke-width="2" stroke-dasharray="12 12"/>
  <circle cx="320" cy="176" r="52" fill="#d8d3c5"/>
  <path d="M232 288c34-58 62-86 86-86 16 0 32 13 49 39 10 15 19 22 29 22 14 0 28-12 43-36l52 61H232z" fill="#c9c1ae"/>
  <text x="320" y="342" text-anchor="middle" fill="#1f3d36" font-family="Arial, sans-serif" font-size="28" font-weight="700">Foto tidak tersedia</text>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function photoUrl(int $photoId): string
    {
        return URL::temporarySignedRoute('photos.raw', now()->addHours(12), ['photo' => $photoId]);
    }

    private function storageDisk(): string
    {
        $disk = config('sim-kk.storage.disk', 'local');
        return $disk === 'local' ? 'public' : $disk;
    }
}
