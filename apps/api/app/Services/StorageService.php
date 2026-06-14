<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    public function storeClinicalPhoto(string $recordId, string $filename, string $content): string
    {
        $safeRecord = preg_replace('/[^a-zA-Z0-9._-]/', '-', $recordId);
        $safeName   = preg_replace('/[^a-zA-Z0-9._-]/', '-', $filename) ?: 'clinical-photo';
        $key = sprintf('clinical/%s/%s-%s', $safeRecord, Str::uuid()->toString(), $safeName);

        $bytes = $this->decodeContent($content);
        $disk = config('sim-kk.storage.disk', 'local');

        if ($disk === 'local') {
            Storage::disk('public')->put($key, $bytes);
        } else {
            Storage::disk($disk)->put($key, $bytes, ['visibility' => 'private']);
        }

        return $key;
    }

    /**
     * Resolve a stored object_ref to a URL the browser can render.
     * - R2 / S3 disks -> presigned temporary URL (10 min).
     * - Local disk -> /storage/ public URL.
     * - Legacy "local://..." refs (old seeder) -> null so caller can show placeholder.
     */
    public function getUrl(?string $objectRef): ?string
    {
        if (!$objectRef) return null;
        if (str_starts_with($objectRef, 'local://')) return null; // legacy seed

        $disk = config('sim-kk.storage.disk', 'local');
        if ($disk === 'local') {
            return asset('storage/' . ltrim($objectRef, '/'));
        }
        // R2 / S3: use presigned URL
        try {
            return Storage::disk($disk)->temporaryUrl(
                $objectRef,
                now()->addMinutes(10)
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Decode a content payload (raw bytes or data: URL) for pre-storage validation.
     * Returns null when decoding fails.
     */
    public function decodeForValidation(string $content): ?string
    {
        $bytes = $this->decodeContent($content);
        return $bytes === '' ? null : $bytes;
    }

    private function decodeContent(string $content): string
    {
        if (str_starts_with($content, 'data:')) {
            $comma = strpos($content, ',');
            if ($comma !== false) {
                $content = substr($content, $comma + 1);
            }
        }
        // Always try base64 decode; if it fails (not b64), treat as raw bytes.
        $decoded = base64_decode($content, true);
        if ($decoded !== false && $decoded !== '') {
            return $decoded;
        }
        return $content;
    }
}
