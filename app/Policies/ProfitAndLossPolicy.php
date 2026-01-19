<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProfitAndLoss;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class ProfitAndLossPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view profit and losses');
    }

    public function view(User $user, ProfitAndLoss $profitAndLoss): bool
    {
        return $user->belongsToTeam($profitAndLoss->team)
            && $user->hasPermissionTo('view profit and losses');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create profit and losses');
    }

    public function update(User $user, ProfitAndLoss $profitAndLoss): bool
    {
        return $user->belongsToTeam($profitAndLoss->team)
            && $user->hasPermissionTo('update profit and losses');
    }

    public function delete(User $user, ProfitAndLoss $profitAndLoss): bool
    {
        return $user->belongsToTeam($profitAndLoss->team)
            && $user->hasPermissionTo('delete profit and losses');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete profit and losses');
    }

    public function restore(User $user, ProfitAndLoss $profitAndLoss): bool
    {
        return $user->belongsToTeam($profitAndLoss->team)
            && $user->hasPermissionTo('update profit and losses');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update profit and losses');
    }

    public function forceDelete(User $user, ProfitAndLoss $profitAndLoss): bool
    {
        return $user->belongsToTeam($profitAndLoss->team)
            && $user->hasPermissionTo('delete profit and losses');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete profit and losses');
    }
}
