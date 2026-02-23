<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Request;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class MediaPolicy
{
    use HandlesAuthorization;

    /**
     * Get the Request model that owns this media.
     */
    private function getRequest(Media $media): ?Request
    {
        if ($media->model_type === Request::class) {
            return Request::find($media->model_id);
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        // Allow any team member to view (e.g. Acceptance Report list); restrict other actions via view/update/delete
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null;
    }

    public function view(User $user, Media $media): bool
    {
        $request = $this->getRequest($media);

        if ($request === null) {
            return false;
        }

        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('view requests');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function update(User $user, Media $media): bool
    {
        $request = $this->getRequest($media);

        if ($request === null) {
            return false;
        }

        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function delete(User $user, Media $media): bool
    {
        $request = $this->getRequest($media);

        if ($request === null) {
            return false;
        }

        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function restore(User $user, Media $media): bool
    {
        $request = $this->getRequest($media);

        if ($request === null) {
            return false;
        }

        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function forceDelete(User $user, Media $media): bool
    {
        $request = $this->getRequest($media);

        if ($request === null) {
            return false;
        }

        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('delete requests');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete requests');
    }
}
