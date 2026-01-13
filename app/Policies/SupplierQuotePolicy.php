<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierQuote;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class SupplierQuotePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view supplier quotes');
    }

    public function view(User $user, SupplierQuote $supplierQuote): bool
    {
        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('view supplier quotes');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create supplier quotes');
    }

    public function update(User $user, SupplierQuote $supplierQuote): bool
    {
        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function delete(User $user, SupplierQuote $supplierQuote): bool
    {
        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('delete supplier quotes');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier quotes');
    }

    public function restore(User $user, SupplierQuote $supplierQuote): bool
    {
        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function forceDelete(User $user, SupplierQuote $supplierQuote): bool
    {
        return $user->belongsToTeam($supplierQuote->team)
            && $user->hasPermissionTo('delete supplier quotes');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier quotes');
    }
}
