<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SupplierQuote;
use App\Models\User;
use App\Support\Media\DocumentResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class SupplierQuoteQuotationDownloadController
{
    public function __invoke(Request $request, SupplierQuote $supplierQuote, Media $media): BinaryFileResponse
    {
        // Check both morph alias and full class name (Spatie stores morph alias when registered)
        $isValidModelType = $media->model_type === SupplierQuote::class
            || $media->model_type === 'supplier_quote';

        if (! $isValidModelType || (int) $media->model_id !== (int) $supplierQuote->id) {
            abort(404);
        }

        if ($media->collection_name !== 'quotation') {
            abort(404);
        }

        $user = $request->user();

        if (! $user instanceof User || ! $user->belongsToTeam($supplierQuote->team)) {
            abort(404);
        }

        $filePath = $media->getPath();

        if (! file_exists($filePath)) {
            abort(404);
        }

        return DocumentResponse::make($media, $filePath);
    }
}
