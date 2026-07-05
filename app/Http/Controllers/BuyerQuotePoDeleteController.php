<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BuyerQuote;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class BuyerQuotePoDeleteController
{
    public function __invoke(Request $request, BuyerQuote $buyerQuote, Media $media): JsonResponse|RedirectResponse
    {
        // Verify ownership
        // Check both morph alias and full class name (Spatie stores it as morph alias)
        $isValidModelType = $media->model_type === BuyerQuote::class
            || $media->model_type === 'buyer_quote';

        if (! $isValidModelType || (int) $media->model_id !== (int) $buyerQuote->id) {
            abort(404);
        }

        if ($media->collection_name !== 'buyer_po') {
            abort(404);
        }

        // Check authorization - user must belong to the buyer quote's team
        $user = $request->user();

        if (! $user instanceof User || ! $user->belongsToTeam($buyerQuote->team)) {
            abort(404);
        }

        try {
            $media->delete();

            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'File deleted successfully',
                ]);
            }

            return redirect()->back()->with('success', 'File deleted successfully');
        } catch (Exception $e) {
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete file: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete file');
        }
    }
}
