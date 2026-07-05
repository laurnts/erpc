<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Support\Media\DocumentPathGenerator;
use App\Support\Media\DocumentPathResolver;
use Illuminate\Support\Facades\Log;
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
     * Caller-supplied $customProperties are merged into each media's custom
     * properties, but the path stamps always win: callers can never override
     * path_prefix / path_version.
     *
     * @param  array<string, mixed>  $customProperties
     * @return list<Media> the created media, ordered, one per successfully attached file
     */
    public function execute(HasMedia $record, mixed $files, string $collection, string $directory, array $customProperties = []): array
    {
        if (! is_array($files)) {
            return [];
        }

        $baseDir = realpath(storage_path('app/'.trim($directory, '/')));

        if ($baseDir === false) {
            return [];
        }

        $properties = [
            ...$customProperties,
            ...$this->pathStampsFor($record, $collection),
        ];

        $attached = [];

        foreach ($files as $file) {
            if (! is_string($file)) {
                continue;
            }

            $realPath = realpath(storage_path('app/'.ltrim($file, '/')));

            if ($realPath === false || ! str_starts_with($realPath, $baseDir.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $attached[] = $record->addMedia($realPath)
                ->withCustomProperties($properties)
                ->toMediaCollection($collection);
        }

        return $attached;
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
