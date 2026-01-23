<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BuyerQuote;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class BuyerQuotePoDownloadController
{
    public function __invoke(Request $request, BuyerQuote $buyerQuote, Media $media): BinaryFileResponse
    {
        // Verify the media belongs to this buyer quote
        // Check both morph alias and full class name (Spatie stores it as morph alias)
        $isValidModelType = $media->model_type === BuyerQuote::class || 
                           $media->model_type === 'buyer_quote' ||
                           $media->model_type === 'App\\Models\\BuyerQuote';
        
        if (!$isValidModelType || (int) $media->model_id !== (int) $buyerQuote->id) {
            abort(404);
        }

        // Verify the media is from the buyer_po collection
        if ($media->collection_name !== 'buyer_po') {
            abort(404);
        }

        // Check authorization - user must be authenticated and have access to the buyer quote
        // You can add more specific authorization checks here if needed
        if (!auth()->check()) {
            abort(403);
        }

        // Get the file path
        $filePath = $media->getPath();

        if (!file_exists($filePath)) {
            abort(404);
        }

        // Determine content type
        $contentType = $media->mime_type ?? 'application/octet-stream';

        // Return file response (opens in browser instead of downloading)
        return response()->file($filePath, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="' . $media->file_name . '"',
        ]);
    }
}
