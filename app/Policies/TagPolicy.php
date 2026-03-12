<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class TagPolicy
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
        return $user->hasVerifiedEmail() && $user->currentTeam !== null;
    }

    public function view(User $user, Tag $tag): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($tag->team);
        }

        return $user->belongsToTeam($tag->team)
            && $user->hasPermissionTo('view tags');
    }

    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create tags');
    }

    public function update(User $user, Tag $tag): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($tag->team);
        }

        return $user->belongsToTeam($tag->team)
            && $user->hasPermissionTo('update tags');
    }

    public function delete(User $user, Tag $tag): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($tag->team);
        }

        return $user->belongsToTeam($tag->team)
            && $user->hasPermissionTo('delete tags');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete tags');
    }

    public function restore(User $user, Tag $tag): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($tag->team);
        }

        return $user->belongsToTeam($tag->team)
            && $user->hasPermissionTo('update tags');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update tags');
    }

    public function forceDelete(User $user, Tag $tag): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($tag->team);
        }

        return $user->belongsToTeam($tag->team)
            && $user->hasPermissionTo('delete tags');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete tags');
    }
}
