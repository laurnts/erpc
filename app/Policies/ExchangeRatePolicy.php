<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class ExchangeRatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view exchange rates');
    }

    public function view(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->belongsToTeam($exchangeRate->team)
            && $user->hasPermissionTo('view exchange rates');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create exchange rates');
    }

    public function update(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->belongsToTeam($exchangeRate->team)
            && $user->hasPermissionTo('update exchange rates');
    }

    public function delete(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->belongsToTeam($exchangeRate->team)
            && $user->hasPermissionTo('delete exchange rates');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete exchange rates');
    }

    public function restore(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->belongsToTeam($exchangeRate->team)
            && $user->hasPermissionTo('update exchange rates');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update exchange rates');
    }

    public function forceDelete(User $user, ExchangeRate $exchangeRate): bool
    {
        return $user->belongsToTeam($exchangeRate->team)
            && $user->hasPermissionTo('delete exchange rates');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete exchange rates');
    }
}
