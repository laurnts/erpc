<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierQuote;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class SupplierQuotePolicy
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
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view supplier quotes');
    }

    public function view(User $user, SupplierQuote $supplierQuote): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierQuote->team);
        }

        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('view supplier quotes');
    }

    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create supplier quotes');
    }

    public function update(User $user, SupplierQuote $supplierQuote): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierQuote->team);
        }

        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function delete(User $user, SupplierQuote $supplierQuote): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierQuote->team);
        }

        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('delete supplier quotes');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier quotes');
    }

    public function restore(User $user, SupplierQuote $supplierQuote): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierQuote->team);
        }

        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function forceDelete(User $user, SupplierQuote $supplierQuote): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierQuote->team);
        }

        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('delete supplier quotes');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier quotes');
    }
}
