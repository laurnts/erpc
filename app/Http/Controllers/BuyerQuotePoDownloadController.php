<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BuyerQuote;
use App\Models\User;
use App\Support\Media\DocumentResponse;
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
                           $media->model_type === 'buyer_quote';

        if (! $isValidModelType || (int) $media->model_id !== (int) $buyerQuote->id) {
            abort(404);
        }

        // Verify the media is from the buyer_po collection
        if ($media->collection_name !== 'buyer_po') {
            abort(404);
        }

        // Check authorization - user must belong to the buyer quote's team
        $user = $request->user();

        if (! $user instanceof User || ! $user->belongsToTeam($buyerQuote->team)) {
            abort(404);
        }

        // Get the file path
        $filePath = $media->getPath();

        if (! file_exists($filePath)) {
            abort(404);
        }

        // Serve inline only for render-safe mime types; force download otherwise
        return DocumentResponse::make($media, $filePath);
    }
}
