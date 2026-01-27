<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class UnitOfMeasurePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view unit of measures');
    }

    public function view(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->belongsToTeam($unitOfMeasure->team)
            && $user->hasPermissionTo('view unit of measures');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create unit of measures');
    }

    public function update(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->belongsToTeam($unitOfMeasure->team)
            && $user->hasPermissionTo('update unit of measures');
    }

    public function delete(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->belongsToTeam($unitOfMeasure->team)
            && $user->hasPermissionTo('delete unit of measures');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete unit of measures');
    }
}
