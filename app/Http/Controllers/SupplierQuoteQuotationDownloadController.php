<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SupplierQuote;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class SupplierQuoteQuotationDownloadController
{
    public function __invoke(Request $request, SupplierQuote $supplierQuote, Media $media): BinaryFileResponse
    {
        $isValidModelType = $media->model_type === SupplierQuote::class
            || $media->model_type === 'App\\Models\\SupplierQuote';

        if (! $isValidModelType || (int) $media->model_id !== (int) $supplierQuote->id) {
            abort(404);
        }

        if ($media->collection_name !== 'quotation') {
            abort(404);
        }

        if (! auth()->check()) {
            abort(403);
        }

        $filePath = $media->getPath();

        if (! file_exists($filePath)) {
            abort(404);
        }

        $contentType = $media->mime_type ?? 'application/octet-stream';

        return response()->file($filePath, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }
}
