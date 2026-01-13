<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RequestActivity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class RequestActivityPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view requests');
    }

    public function view(User $user, RequestActivity $activity): bool
    {
        return $user->belongsToTeam($activity->team)
            && $user->hasPermissionTo('view requests');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function update(User $user, RequestActivity $activity): bool
    {
        return $user->belongsToTeam($activity->team)
            && $user->hasPermissionTo('update requests');
    }

    public function delete(User $user, RequestActivity $activity): bool
    {
        return $user->belongsToTeam($activity->team)
            && $user->hasPermissionTo('update requests');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function restore(User $user, RequestActivity $activity): bool
    {
        return $user->belongsToTeam($activity->team)
            && $user->hasPermissionTo('update requests');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function forceDelete(User $user, RequestActivity $activity): bool
    {
        return $user->belongsToTeam($activity->team)
            && $user->hasPermissionTo('update requests');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }
}
