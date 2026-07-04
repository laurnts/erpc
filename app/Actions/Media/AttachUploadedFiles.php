<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Support\Media\DocumentPathGenerator;
use Spatie\MediaLibrary\HasMedia;

final readonly class AttachUploadedFiles
{
    /**
     * Attach Filament FileUpload state (relative storage paths) to a model's
     * media collection, tagging each file with the v2 document path version.
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

        foreach ($files as $file) {
            if (! is_string($file)) {
                continue;
            }

            $realPath = realpath(storage_path('app/'.ltrim($file, '/')));

            if ($realPath === false || ! str_starts_with($realPath, $baseDir.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $record->addMedia($realPath)
                ->withCustomProperties([DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2])
                ->toMediaCollection($collection);
        }
    }
}
