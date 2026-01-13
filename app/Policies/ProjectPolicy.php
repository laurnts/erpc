<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class ProjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view projects');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team)
            && $user->hasPermissionTo('view projects');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create projects');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team)
            && $user->hasPermissionTo('update projects');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team)
            && $user->hasPermissionTo('delete projects');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete projects');
    }

    public function restore(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team)
            && $user->hasPermissionTo('update projects');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update projects');
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->team)
            && $user->hasPermissionTo('delete projects');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete projects');
    }
}
