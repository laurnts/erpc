<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RequestItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class RequestItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view requests');
    }

    public function view(User $user, RequestItem $requestItem): bool
    {
        return $user->belongsToTeam($requestItem->request->team)
            && $user->hasPermissionTo('view requests');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function update(User $user, RequestItem $requestItem): bool
    {
        return $user->belongsToTeam($requestItem->request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function delete(User $user, RequestItem $requestItem): bool
    {
        return $user->belongsToTeam($requestItem->request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function restore(User $user, RequestItem $requestItem): bool
    {
        return $user->belongsToTeam($requestItem->request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function forceDelete(User $user, RequestItem $requestItem): bool
    {
        return $user->belongsToTeam($requestItem->request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function reorder(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }
}
