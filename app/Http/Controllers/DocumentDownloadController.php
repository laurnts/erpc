<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\User;
use App\Support\Media\DocumentResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class DocumentDownloadController
{
    public function __invoke(Request $request, Media $media): BinaryFileResponse
    {
        $model = $media->model;

        $user = $request->user();

        if (! $user instanceof User || $model === null || ! isset($model->team_id)) {
            abort(404);
        }

        $team = Team::query()->find($model->team_id);

        if (! $team instanceof Team || ! $user->belongsToTeam($team)) {
            abort(404);
        }

        $filePath = $media->getPath();

        if (! file_exists($filePath)) {
            abort(404);
        }

        return DocumentResponse::make($media, $filePath, forceDownload: $request->boolean('download'));
    }
}
