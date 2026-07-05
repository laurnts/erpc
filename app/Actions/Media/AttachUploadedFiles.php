<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Support\Media\DocumentPathGenerator;
use App\Support\Media\DocumentPathResolver;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;

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
     */
    public function execute(HasMedia $record, mixed $files, string $collection, string $directory): void
    {
        if (! is_array($files)) {
            return;
        }

        $baseDir = realpath(storage_path('app/'.trim($directory, '/')));

        if ($baseDir === false) {
            return;
        }

        $customProperties = $this->customPropertiesFor($record, $collection);

        foreach ($files as $file) {
            if (! is_string($file)) {
                continue;
            }

            $realPath = realpath(storage_path('app/'.ltrim($file, '/')));

            if ($realPath === false || ! str_starts_with($realPath, $baseDir.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $record->addMedia($realPath)
                ->withCustomProperties($customProperties)
                ->toMediaCollection($collection);
        }
    }

    /**
     * Resolve the custom properties to stamp: v3 prefix when the chain resolves,
     * otherwise a v2 fallback (logged).
     *
     * @return array<string, mixed>
     */
    private function customPropertiesFor(HasMedia $record, string $collection): array
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
