<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitOfMeasure;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class UnitOfMeasurePolicy
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

    public function view(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($unitOfMeasure->team);
        }

        return $user->belongsToTeam($unitOfMeasure->team)
            && $user->hasPermissionTo('view unit of measures');
    }

    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create unit of measures');
    }

    public function update(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($unitOfMeasure->team);
        }

        return $user->belongsToTeam($unitOfMeasure->team)
            && $user->hasPermissionTo('update unit of measures');
    }

    public function delete(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($unitOfMeasure->team);
        }

        return $user->belongsToTeam($unitOfMeasure->team)
            && $user->hasPermissionTo('delete unit of measures');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete unit of measures');
    }
}
