<?php

namespace App\Console\Commands;

use App\Models\FotoKlinis;
use App\Services\StorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigratePhotosToR2 extends Command
{
    protected $signature = 'simkk:migrate-photos-to-r2 {--source=public : Source disk (where local photos live)}';
    protected $description = 'One-shot migration of local clinical photos to Cloudflare R2. Sets STORAGE_DISK=r2 first.';

    public function handle(StorageService $storage): int
    {
        $source = (string) $this->option('source');
        $this->info("Migrating photos from disk [{$source}] to R2...");

        $count = 0;
        foreach (FotoKlinis::all() as $foto) {
            if (!str_starts_with($foto->object_ref, 'local://')) {
                $this->line("  SKIP #{$foto->id} (already remote: {$foto->object_ref})");
                continue;
            }
            $relative = substr($foto->object_ref, strlen('local://'));
            $sourcePath = "clinical/{$foto->pasien->rekam_medis_id}/" . basename($relative);
            $sourcePath = str_replace('\\', '/', $sourcePath);

            if (!Storage::disk($source)->exists($sourcePath)) {
                $this->warn("  MISSING source for #{$foto->id}: {$sourcePath}");
                continue;
            }

            $bytes = Storage::disk($source)->get($sourcePath);
            $newKey = $storage->storeClinicalPhoto(
                $foto->pasien->rekam_medis_id,
                basename($sourcePath),
                $bytes,
            );
            $foto->object_ref = $newKey;
            $foto->save();
            $count++;
            $this->line("  OK #{$foto->id} → {$newKey}");
        }

        $this->info("Done. Migrated {$count} photos.");
        return self::SUCCESS;
    }
}
