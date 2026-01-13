<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TaxCode;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class TaxCodePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view tax codes');
    }

    public function view(User $user, TaxCode $taxCode): bool
    {
        return $user->belongsToTeam($taxCode->team)
            && $user->hasPermissionTo('view tax codes');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create tax codes');
    }

    public function update(User $user, TaxCode $taxCode): bool
    {
        return $user->belongsToTeam($taxCode->team)
            && $user->hasPermissionTo('update tax codes');
    }

    public function delete(User $user, TaxCode $taxCode): bool
    {
        return $user->belongsToTeam($taxCode->team)
            && $user->hasPermissionTo('delete tax codes');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete tax codes');
    }

    public function restore(User $user, TaxCode $taxCode): bool
    {
        return $user->belongsToTeam($taxCode->team)
            && $user->hasPermissionTo('update tax codes');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update tax codes');
    }

    public function forceDelete(User $user, TaxCode $taxCode): bool
    {
        return $user->belongsToTeam($taxCode->team)
            && $user->hasPermissionTo('delete tax codes');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete tax codes');
    }
}
