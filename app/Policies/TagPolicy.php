<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class TagPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view tags');
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->belongsToTeam($tag->team)
            && $user->hasPermissionTo('view tags');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create tags');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->belongsToTeam($tag->team)
            && $user->hasPermissionTo('update tags');
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->belongsToTeam($tag->team)
            && $user->hasPermissionTo('delete tags');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete tags');
    }

    public function restore(User $user, Tag $tag): bool
    {
        return $user->belongsToTeam($tag->team)
            && $user->hasPermissionTo('update tags');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update tags');
    }

    public function forceDelete(User $user, Tag $tag): bool
    {
        return $user->belongsToTeam($tag->team)
            && $user->hasPermissionTo('delete tags');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete tags');
    }
}
