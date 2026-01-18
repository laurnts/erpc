<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\KeyAccount;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class KeyAccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view key accounts');
    }

    public function view(User $user, KeyAccount $keyAccount): bool
    {
        return $user->belongsToTeam($keyAccount->team)
            && $user->hasPermissionTo('view key accounts');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create key accounts');
    }

    public function update(User $user, KeyAccount $keyAccount): bool
    {
        return $user->belongsToTeam($keyAccount->team)
            && $user->hasPermissionTo('update key accounts');
    }

    public function delete(User $user, KeyAccount $keyAccount): bool
    {
        return $user->belongsToTeam($keyAccount->team)
            && $user->hasPermissionTo('delete key accounts');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete key accounts');
    }

    public function restore(User $user, KeyAccount $keyAccount): bool
    {
        return $user->belongsToTeam($keyAccount->team)
            && $user->hasPermissionTo('update key accounts');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update key accounts');
    }

    public function forceDelete(User $user, KeyAccount $keyAccount): bool
    {
        return $user->belongsToTeam($keyAccount->team)
            && $user->hasPermissionTo('delete key accounts');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete key accounts');
    }
}
