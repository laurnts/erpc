<?php

declare(strict_types=1);

namespace App\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Builds a safe file response for a stored document.
 *
 * Only render-safe mime types are served inline with their stored mime;
 * everything else (e.g. image/svg+xml, text/html) is forced to download
 * as application/octet-stream to prevent stored XSS on the app origin.
 */
final readonly class DocumentResponse
{
    private const array INLINE_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
    ];

    public static function make(Media $media, string $filePath): BinaryFileResponse
    {
        $isInline = in_array($media->mime_type, self::INLINE_MIME_TYPES, true);

        $contentType = $isInline ? $media->mime_type : 'application/octet-stream';
        $disposition = $isInline ? 'inline' : 'attachment';
        $fileName = str_replace(['"', "\r", "\n"], '', $media->file_name);

        return response()->file($filePath, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition.'; filename="'.$fileName.'"',
        ]);
    }
}
