<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierQuoteItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class SupplierQuoteItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view supplier quotes');
    }

    public function view(User $user, SupplierQuoteItem $item): bool
    {
        return $user->belongsToTeam($item->supplierQuote->team)
            && $user->hasPermissionTo('view supplier quotes');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function update(User $user, SupplierQuoteItem $item): bool
    {
        return $user->belongsToTeam($item->supplierQuote->team)
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function delete(User $user, SupplierQuoteItem $item): bool
    {
        return $user->belongsToTeam($item->supplierQuote->team)
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function restore(User $user, SupplierQuoteItem $item): bool
    {
        return $user->belongsToTeam($item->supplierQuote->team)
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function forceDelete(User $user, SupplierQuoteItem $item): bool
    {
        return $user->belongsToTeam($item->supplierQuote->team)
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier quotes');
    }

    public function reorder(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier quotes');
    }
}
