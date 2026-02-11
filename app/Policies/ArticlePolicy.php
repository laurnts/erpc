<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Article;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class ArticlePolicy
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
            && $user->hasPermissionTo('view articles');
    }

    public function view(User $user, Article $article): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($article->team);
        }

        return $user->belongsToTeam($article->team)
            && $user->hasPermissionTo('view articles');
    }

    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create articles');
    }

    public function update(User $user, Article $article): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($article->team);
        }

        return $user->belongsToTeam($article->team)
            && $user->hasPermissionTo('update articles');
    }

    public function delete(User $user, Article $article): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($article->team);
        }

        return $user->belongsToTeam($article->team)
            && $user->hasPermissionTo('delete articles');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete articles');
    }

    public function restore(User $user, Article $article): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($article->team);
        }

        return $user->belongsToTeam($article->team)
            && $user->hasPermissionTo('update articles');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update articles');
    }

    public function forceDelete(User $user, Article $article): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($article->team);
        }

        return $user->belongsToTeam($article->team)
            && $user->hasPermissionTo('delete articles');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete articles');
    }
}
