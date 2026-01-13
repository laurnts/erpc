<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class CurrencyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view currencies');
    }

    public function view(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view currencies');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create currencies');
    }

    public function update(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update currencies');
    }

    public function delete(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete currencies');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete currencies');
    }

    public function restore(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update currencies');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update currencies');
    }

    public function forceDelete(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete currencies');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete currencies');
    }
}
