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
     */
    public function execute(HasMedia $record, mixed $files, string $collection): void
    {
        if (! is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if (! is_string($file)) {
                continue;
            }

            $filePath = storage_path('app/'.ltrim($file, '/'));

            if (file_exists($filePath)) {
                $record->addMedia($filePath)
                    ->withCustomProperties([DocumentPathGenerator::PATH_VERSION_PROPERTY => DocumentPathGenerator::PATH_VERSION_V2])
                    ->toMediaCollection($collection);
            }
        }
    }
}
