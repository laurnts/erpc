<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaxCode;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class TaxCodePolicy
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

    public function view(User $user, TaxCode $taxCode): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($taxCode->team);
        }

        return $user->belongsToTeam($taxCode->team)
            && $user->hasPermissionTo('view tax codes');
    }

    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create tax codes');
    }

    public function update(User $user, TaxCode $taxCode): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($taxCode->team);
        }

        return $user->belongsToTeam($taxCode->team)
            && $user->hasPermissionTo('update tax codes');
    }

    public function delete(User $user, TaxCode $taxCode): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($taxCode->team);
        }

        return $user->belongsToTeam($taxCode->team)
            && $user->hasPermissionTo('delete tax codes');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete tax codes');
    }

    public function restore(User $user, TaxCode $taxCode): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($taxCode->team);
        }

        return $user->belongsToTeam($taxCode->team)
            && $user->hasPermissionTo('update tax codes');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update tax codes');
    }

    public function forceDelete(User $user, TaxCode $taxCode): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($taxCode->team);
        }

        return $user->belongsToTeam($taxCode->team)
            && $user->hasPermissionTo('delete tax codes');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete tax codes');
    }
}
