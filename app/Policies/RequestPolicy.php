<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Request;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class RequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view requests');
    }

    public function view(User $user, Request $request): bool
    {
        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('view requests');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create requests');
    }

    public function update(User $user, Request $request): bool
    {
        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function delete(User $user, Request $request): bool
    {
        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('delete requests');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete requests');
    }

    public function restore(User $user, Request $request): bool
    {
        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function forceDelete(User $user, Request $request): bool
    {
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
