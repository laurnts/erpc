<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Media\DocumentPathGenerator;
use App\Support\Media\DocumentPathResolver;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;
use Throwable;

/**
 * Idempotently moves pre-v3 document media on the local disk into their
 * request-anchored v3 paths and stamps them with `path_prefix` / `path_version`.
 *
 * Candidates are found by a live query (never a hardcoded id list), so the
 * command is safe to re-run: once a media row is stamped v3 it drops out of
 * the candidate set on the next run.
 *
 * A crash between the stamp commit and the old-directory delete leaves a
 * stale source directory behind; such leftovers are surfaced by a subsequent
 * `documents:scan-orphans` run.
 */
final class MigrateDocumentsToV3Command extends Command
{
    protected $signature = 'documents:migrate-v3';

    protected $description = 'Move pre-v3 documents on the local disk into their v3 request-anchored paths and stamp them accordingly';

    public function handle(): int
    {
        $disk = Storage::disk('local');

        $candidates = Media::query()
            ->where('disk', 'local')
            ->get()
            ->reject($this->isAlreadyV3(...));

        $migrated = 0;
        $skipped = 0;

        foreach ($candidates as $media) {
            $model = $media->model;

            if (! $model instanceof HasMedia) {
                $this->line("SKIP media #{$media->getKey()}: owning model is missing");
                $skipped++;

                continue;
            }

            $prefix = DocumentPathResolver::prefixFor($model, $media->collection_name);

            if ($prefix === null) {
                $this->line("SKIP media #{$media->getKey()}: v3 path could not be resolved");
                $skipped++;

                continue;
            }

            try {
                [$oldDir, $newDir] = $this->migrateOne($media, $disk, $prefix);
                $this->line("media #{$media->getKey()}: {$oldDir} -> {$newDir}");
                $migrated++;
            } catch (Throwable $e) {
                $this->line("SKIP media #{$media->getKey()}: {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->info("Migrated {$migrated} media item(s), skipped {$skipped}.");

        return self::SUCCESS;
    }

    private function isAlreadyV3(Media $media): bool
    {
        $version = $media->getCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY);

        return $version === DocumentPathGenerator::PATH_VERSION_V3 || $version === (string) DocumentPathGenerator::PATH_VERSION_V3;
    }

    /**
     * Stamp the media with its resolved v3 prefix and relocate its directory
     * on disk using a copy-first strategy: copy every file to the new
     * directory and verify it, then commit the stamp in a transaction, and
     * only then delete the old directory. A failure at any point before the
     * delete leaves the original tree fully intact and the media unstamped,
     * so DB and disk never disagree about where a live file is; stray copies
     * left in the target by an interrupted run are overwritten on rerun.
     *
     * The old path is captured BEFORE stamping (stamping changes what the
     * generator resolves).
     *
     * @return array{0: string, 1: string} old directory, new directory
     */
    private function migrateOne(Media $media, Filesystem $disk, string $prefix): array
    {
        $generator = PathGeneratorFactory::create($media);
        $oldDir = rtrim($generator->getPath($media), '/');
        $originalProperties = $media->custom_properties;

        $media->setCustomProperty(DocumentPathGenerator::PATH_VERSION_PROPERTY, DocumentPathGenerator::PATH_VERSION_V3);
        $media->setCustomProperty(DocumentPathGenerator::PATH_PREFIX_PROPERTY, $prefix);

        $newDir = rtrim($generator->getPath($media), '/');

        try {
            if ($oldDir !== $newDir) {
                $this->copyDirectory($disk, $oldDir, $newDir);
            }

            DB::transaction(fn () => $media->save());
        } catch (Throwable $e) {
            $media->custom_properties = $originalProperties;

            throw $e;
        }

        if ($oldDir !== $newDir && ! $disk->deleteDirectory($oldDir)) {
            $this->warn("media #{$media->getKey()}: migrated, but could not remove old directory {$oldDir}");
        }

        return [$oldDir, $newDir];
    }

    /**
     * Copy every file under $oldDir (including nested conversions/ and
     * responsive-images/ subdirectories) to the equivalent path under $newDir,
     * verifying each copy. Never mutates $oldDir; a pre-existing file at a
     * target path (a stray from an interrupted earlier run) is replaced by
     * the fresh copy.
     */
    private function copyDirectory(Filesystem $disk, string $oldDir, string $newDir): void
    {
        if (! $disk->exists($oldDir)) {
            return;
        }

        foreach ($disk->allFiles($oldDir) as $oldFile) {
            $relative = ltrim(substr($oldFile, strlen($oldDir)), '/');
            $newFile = $newDir.'/'.$relative;

            if ($disk->exists($newFile)) {
                $disk->delete($newFile);
            }

            if (! $disk->copy($oldFile, $newFile)
                || ! $disk->exists($newFile)
                || $disk->size($newFile) !== $disk->size($oldFile)
            ) {
                throw new RuntimeException("failed to copy {$oldFile} to {$newFile}");
            }
        }
    }
}
