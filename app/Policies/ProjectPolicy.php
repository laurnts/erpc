<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class ProjectPolicy
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
            && $user->hasPermissionTo('view projects');
    }

    public function view(User $user, Project $project): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($project->team);
        }

        return $user->belongsToTeam($project->team)
            && $user->hasPermissionTo('view projects');
    }

    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create projects');
    }

    public function update(User $user, Project $project): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($project->team);
        }

        return $user->belongsToTeam($project->team)
            && $user->hasPermissionTo('update projects');
    }

    public function delete(User $user, Project $project): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($project->team);
        }

        return $user->belongsToTeam($project->team)
            && $user->hasPermissionTo('delete projects');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete projects');
    }

    public function restore(User $user, Project $project): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($project->team);
        }

        return $user->belongsToTeam($project->team)
            && $user->hasPermissionTo('update projects');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update projects');
    }

    public function forceDelete(User $user, Project $project): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($project->team);
        }

        return $user->belongsToTeam($project->team)
            && $user->hasPermissionTo('delete projects');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete projects');
    }
}
