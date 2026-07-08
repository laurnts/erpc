<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Support\ActivityLogContext;
use App\Support\Media\DocumentPathGenerator;
use App\Support\Media\DocumentPathResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class AttachUploadedFiles
{
    /**
     * Attach Filament FileUpload state (relative storage paths) to a model's
     * media collection, stamping each file with the resolved v3 document path
     * (prefix + version) so path resolution stays query-free and stable if the
     * parent is later renumbered. When the anchoring chain cannot be resolved,
     * fall back to the v2 path version.
     *
     * File paths arrive from Livewire form state and are attacker-controllable
     * in tampered payloads, so each path must resolve inside the upload
     * directory the corresponding FileUpload component writes to.
     *
     * The path stamps are resolved lazily, on (or before) the first
     * successfully validated file: resolution may log a v2-fallback warning,
     * and a call where every path is rejected must not attach anything and
     * must not log that warning either.
     *
     * Caller-supplied $customProperties are merged into each media's custom
     * properties, but the path stamps always win: callers can never override
     * path_prefix / path_version.
     *
     * Each media is also stamped with the uploader's identity (uploader_id /
     * uploader_actor_type from {@see ActivityLogContext}) so downstream views
     * can attribute uploads; media without a stamp is treated as System /
     * Unknown. Unlike the path stamps, a caller-supplied uploader_id or
     * uploader_actor_type in $customProperties wins over the resolved value.
     *
     * @param  array<string, mixed>  $customProperties
     * @return list<Media> the created media, ordered, one per successfully attached file
     */
    public function execute(HasMedia $record, mixed $files, string $collection, string $directory, array $customProperties = []): array
    {
        if (! is_array($files)) {
            return [];
        }

        $root = rtrim(Storage::disk('local')->path(''), DIRECTORY_SEPARATOR);
        $baseDir = realpath($root.DIRECTORY_SEPARATOR.trim($directory, '/'));

        if ($baseDir === false) {
            return [];
        }

        $attached = [];
        $stamps = null;
        $uploaderStamps = $this->uploaderStamps();

        foreach ($files as $file) {
            if (! is_string($file)) {
                continue;
            }

            $realPath = realpath($root.DIRECTORY_SEPARATOR.ltrim($file, '/'));

            if ($realPath === false || ! str_starts_with($realPath, $baseDir.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $stamps ??= $this->pathStampsFor($record, $collection);

            $attached[] = $record->addMedia($realPath)
                ->withCustomProperties([
                    ...$uploaderStamps,
                    ...$customProperties,
                    ...$stamps,
                ])
                ->toMediaCollection($collection);
        }

        return $attached;
    }

    /**
     * Identity of the current actor at attach time; uploader_id stays null
     * when no causer resolves (System uploads).
     *
     * @return array{uploader_id: int|string|null, uploader_actor_type: string}
     */
    private function uploaderStamps(): array
    {
        return [
            'uploader_id' => ActivityLogContext::currentCauser()?->getKey(),
            'uploader_actor_type' => ActivityLogContext::currentActorType()->value,
        ];
    }

    /**
     * Resolve the path stamps: v3 prefix when the chain resolves, otherwise a
     * v2 fallback (logged).
     *
     * @return array<string, mixed>
     */
    private function pathStampsFor(HasMedia $record, string $collection): array
    {
        $prefix = DocumentPathResolver::prefixFor($record, $collection);

        if ($prefix !== null) {
            return [
                DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V3,
                DocumentPathGenerator::PATH_PREFIX_PROPERTY => $prefix,
            ];
        }

        Log::warning('Document path fallback to v2', [
            'model' => $record::class,
            'model_id' => $record->getKey(),
            'collection' => $collection,
        ]);

        return [DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2];
    }
}
