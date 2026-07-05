<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

/**
 * Scans the `local` and `public` disks for document files that no longer
 * belong to any media row and reports (or, with --delete, removes) them.
 *
 * The referenced set is built per disk from every media row's
 * generator-resolved directory (main file + conversions + responsive
 * images) rather than from id-folder presence: an id can exist under two
 * different disks for two unrelated records, so disk identity is part of
 * the match, not just the folder name.
 */
final class ScanOrphanDocumentsCommand extends Command
{
    /** @var list<string> */
    private const DISKS = ['local', 'public'];

    /** @var list<string> */
    private const SKIPPED_SEGMENTS = ['uploads-tmp', 'livewire-tmp'];

    protected $signature = 'documents:scan-orphans {--delete : Delete orphaned files instead of only reporting them}';

    protected $description = 'Scan document disks for files no longer referenced by any media row';

    public function handle(): int
    {
        $referenced = $this->buildReferencedDirectories();
        $delete = (bool) $this->option('delete');

        $totalOrphans = 0;
        $totalDeleted = 0;

        foreach (self::DISKS as $diskName) {
            $disk = Storage::disk($diskName);
            $orphans = $this->findOrphans($disk, $diskName, $referenced);

            foreach ($orphans as $orphan) {
                $this->line("orphan [{$diskName}] {$orphan}");
            }
            $totalOrphans += count($orphans);

            if ($delete && $orphans !== []) {
                $deleted = $this->deleteOrphans($disk, $orphans);
                foreach ($deleted as $orphan) {
                    $this->line("deleted [{$diskName}] {$orphan}");
                }
                $totalDeleted += count($deleted);
            }
        }

        $this->info($delete
            ? "Found {$totalOrphans} orphan(s), deleted {$totalDeleted}."
            : "Found {$totalOrphans} orphan(s). Re-run with --delete to remove them.");

        return self::SUCCESS;
    }

    /**
     * @return array<string, list<string>> disk name => referenced directory prefixes (trailing slash)
     */
    private function buildReferencedDirectories(): array
    {
        $referenced = [];

        Media::query()->each(function (Media $media) use (&$referenced): void {
            $generator = PathGeneratorFactory::create($media);

            foreach ([
                $generator->getPath($media),
                $generator->getPathForConversions($media),
                $generator->getPathForResponsiveImages($media),
            ] as $dir) {
                $referenced[$media->disk][] = rtrim($dir, '/').'/';
            }
        });

        foreach ($referenced as $diskName => $dirs) {
            $referenced[$diskName] = array_values(array_unique($dirs));
        }

        return $referenced;
    }

    /**
     * @param  array<string, list<string>>  $referenced
     * @return list<string>
     */
    private function findOrphans(Filesystem $disk, string $diskName, array $referenced): array
    {
        $dirs = $referenced[$diskName] ?? [];
        $orphans = [];

        foreach ($disk->allFiles('') as $file) {
            if ($this->isAlwaysSkipped($diskName, $file)) {
                continue;
            }

            $isReferenced = false;
            foreach ($dirs as $dir) {
                if (str_starts_with($file, $dir)) {
                    $isReferenced = true;

                    break;
                }
            }

            if (! $isReferenced) {
                $orphans[] = $file;
            }
        }

        return $orphans;
    }

    /**
     * Files that are never orphan candidates, regardless of media references:
     * transient upload staging directories, and (on the local disk) the
     * `public/` subtree, which is a real nested directory on disk (the
     * `public` disk's root lives inside the `local` disk's root) already
     * covered by the separate `public` disk scan.
     */
    private function isAlwaysSkipped(string $diskName, string $relativePath): bool
    {
        if (basename($relativePath) === '.gitignore') {
            return true;
        }

        if ($diskName === 'local' && str_starts_with($relativePath, 'public/')) {
            return true;
        }

        foreach (self::SKIPPED_SEGMENTS as $segment) {
            if ($relativePath === $segment
                || str_starts_with($relativePath, $segment.'/')
                || str_contains($relativePath, '/'.$segment.'/')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $orphans
     * @return list<string> the paths actually deleted
     */
    private function deleteOrphans(Filesystem $disk, array $orphans): array
    {
        $deleted = [];
        $touchedDirs = [];

        foreach ($orphans as $path) {
            if ($disk->delete($path)) {
                $deleted[] = $path;
                $touchedDirs[dirname($path)] = true;
            }
        }

        foreach (array_keys($touchedDirs) as $dir) {
            $this->pruneEmptyAncestors($disk, $dir);
        }

        return $deleted;
    }

    private function pruneEmptyAncestors(Filesystem $disk, string $dir): void
    {
        while ($dir !== '' && $dir !== '.') {
            if ($disk->allFiles($dir) !== [] || $disk->directories($dir) !== []) {
                break;
            }

            $disk->deleteDirectory($dir);
            $dir = dirname($dir);
        }
    }
}
