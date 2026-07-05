<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SupplierQuote;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class SupplierQuoteQuotationDeleteController
{
    public function __invoke(Request $request, SupplierQuote $supplierQuote, Media $media): JsonResponse|RedirectResponse
    {
        // Check both morph alias and full class name (Spatie stores morph alias when registered)
        $isValidModelType = $media->model_type === SupplierQuote::class
            || $media->model_type === 'supplier_quote'
            || $media->model_type === 'App\\Models\\SupplierQuote';

        if (! $isValidModelType || (int) $media->model_id !== (int) $supplierQuote->id) {
            abort(404);
        }

        if ($media->collection_name !== 'quotation') {
            abort(404);
        }

        $user = $request->user();

        if ($user === null || ! $user->belongsToTeam($supplierQuote->team)) {
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
