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
            $decoded = base64_decode($content, true);
            if ($decoded !== false) return $decoded;
        }
        return $content;
    }
}
