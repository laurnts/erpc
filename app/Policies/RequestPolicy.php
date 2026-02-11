<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Request;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class RequestPolicy
{
    use HandlesAuthorization;

    /**
     * Check if user is an administrator for the current team.
     */
    private function isAdmin(User $user): bool
    {
        $team = Filament::getTenant();
        return $team !== null && $user->hasTeamRole($team, 'admin');
    }

    public function viewAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view requests');
    }

    public function view(User $user, Request $request): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($request->team);
        }

        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('view requests');
    }

    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create requests');
    }

    public function update(User $user, Request $request): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($request->team);
        }

        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function delete(User $user, Request $request): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($request->team);
        }

        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('delete requests');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete requests');
    }

    public function restore(User $user, Request $request): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($request->team);
        }

        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('update requests');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update requests');
    }

    public function forceDelete(User $user, Request $request): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($request->team);
        }

        return $user->belongsToTeam($request->team)
            && $user->hasPermissionTo('delete requests');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete requests');
    }
}
