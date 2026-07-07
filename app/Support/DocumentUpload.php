<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Centralized presentation helpers for document-upload fields.
 *
 * Keeps the accepted-format / max-size guidance identical across every
 * upload modal and form so users always see the same standardized rules.
 */
final readonly class DocumentUpload
{
    /**
     * Standard accepted MIME types for document uploads
     * (PDF, Excel, Word, and common image formats).
     *
     * @var list<string>
     */
    public const array ACCEPTED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/png',
        'image/jpeg',
        'image/jpg',
    ];

    /**
     * Human-readable label for the standard accepted formats.
     */
    public const string DEFAULT_FORMATS = 'PDF, Excel, Word, or images';

    /**
     * Build the standardized upload guidance as a bullet list.
     *
     * @param  int  $maxSizeKb  Maximum file size in kilobytes (matches FileUpload::maxSize()).
     * @param  string  $formats  Human-readable accepted formats label.
     * @param  list<string>  $notes  Extra context bullets shown before the format/size rules.
     */
    public static function helperText(int $maxSizeKb, string $formats = self::DEFAULT_FORMATS, array $notes = []): HtmlString
    {
        $bullets = array_merge($notes, [
            "Accepted formats: {$formats}",
            'Maximum size: '.self::formatSize($maxSizeKb).' per file',
        ]);

        $items = array_reduce(
            $bullets,
            fn (string $carry, string $bullet): string => $carry.'<li>'.e($bullet).'</li>',
            '',
        );

        return new HtmlString(
            '<ul class="list-disc list-inside space-y-0.5">'.$items.'</ul>',
        );
    }

    /**
     * Standardized validation message shown when an uploaded file exceeds the size limit.
     *
     * Derives the limit from the same kilobyte value passed to FileUpload::maxSize(),
     * so the message can never disagree with the enforced limit or the helper text.
     *
     * @param  int  $maxSizeKb  Maximum file size in kilobytes (matches FileUpload::maxSize()).
     */
    public static function maxSizeMessage(int $maxSizeKb): string
    {
        return 'Each file must not exceed '.self::formatSize($maxSizeKb).'. Please compress or resize your file before uploading.';
    }

    /**
     * Format a kilobyte size as a compact MB/KB label (e.g. 10240 => "10MB").
     */
    private static function formatSize(int $maxSizeKb): string
    {
        if ($maxSizeKb < 1024) {
            return "{$maxSizeKb}KB";
        }

        $megabytes = rtrim(rtrim(number_format($maxSizeKb / 1024, 2, '.', ''), '0'), '.');

        return "{$megabytes}MB";
    }
}
