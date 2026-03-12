<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExchangeRate;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class ExchangeRatePolicy
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

    public function view(User $user, ExchangeRate $exchangeRate): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($exchangeRate->team);
        }

        return $user->belongsToTeam($exchangeRate->team)
            && $user->hasPermissionTo('view exchange rates');
    }

    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create exchange rates');
    }

    public function update(User $user, ExchangeRate $exchangeRate): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($exchangeRate->team);
        }

        return $user->belongsToTeam($exchangeRate->team)
            && $user->hasPermissionTo('update exchange rates');
    }

    public function delete(User $user, ExchangeRate $exchangeRate): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($exchangeRate->team);
        }

        return $user->belongsToTeam($exchangeRate->team)
            && $user->hasPermissionTo('delete exchange rates');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete exchange rates');
    }

    public function restore(User $user, ExchangeRate $exchangeRate): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($exchangeRate->team);
        }

        return $user->belongsToTeam($exchangeRate->team)
            && $user->hasPermissionTo('update exchange rates');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update exchange rates');
    }

    public function forceDelete(User $user, ExchangeRate $exchangeRate): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($exchangeRate->team);
        }

        return $user->belongsToTeam($exchangeRate->team)
            && $user->hasPermissionTo('delete exchange rates');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete exchange rates');
    }
}
