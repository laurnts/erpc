<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GoodsReceiveBatch;
use App\Models\Request as RequestModel;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class RequestGoodsReceiveDeleteController
{
    public function __invoke(RequestModel $request, Media $media): JsonResponse|RedirectResponse
    {
        // Check both morph alias and full class name (Spatie stores it as morph alias
        // via Relation::enforceMorphMap, so model_type is 'request', not the FQCN)
        $isValidModelType = $media->model_type === RequestModel::class
            || $media->model_type === 'request';

        if (! $isValidModelType || (int) $media->model_id !== (int) $request->id) {
            abort(404);
        }

        if ($media->collection_name !== 'goods_receive') {
            abort(404);
        }

        // Check authorization - user must belong to the request's team
        $user = auth()->user();

        if (! $user instanceof User || ! $user->belongsToTeam($request->team)) {
            abort(404);
        }

        $batch = GoodsReceiveBatch::query()
            ->where('request_id', $request->id)
            ->whereJsonContains('media_ids', $media->id)
            ->first();

        try {
            $media->delete();

            if ($batch !== null) {
                $mediaIds = array_values(array_filter($batch->media_ids ?? [], fn ($id): bool => (int) $id !== (int) $media->id));

                if ($mediaIds === []) {
                    $batch->delete();
                } else {
                    $batch->update(['media_ids' => $mediaIds]);
                }
            }

            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'File deleted successfully',
                ]);
            }

            return redirect()->back()->with('success', 'File deleted successfully');
        } catch (Exception $e) {
            if (request()->wantsJson() || request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete file: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete file');
        }
    }
}
