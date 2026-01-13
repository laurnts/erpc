<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BuyerInvoiceItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerInvoiceItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view buyer invoices');
    }

    public function view(User $user, BuyerInvoiceItem $item): bool
    {
        return $user->belongsToTeam($item->buyerInvoice->team)
            && $user->hasPermissionTo('view buyer invoices');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function update(User $user, BuyerInvoiceItem $item): bool
    {
        return $user->belongsToTeam($item->buyerInvoice->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function delete(User $user, BuyerInvoiceItem $item): bool
    {
        return $user->belongsToTeam($item->buyerInvoice->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function restore(User $user, BuyerInvoiceItem $item): bool
    {
        return $user->belongsToTeam($item->buyerInvoice->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function forceDelete(User $user, BuyerInvoiceItem $item): bool
    {
        return $user->belongsToTeam($item->buyerInvoice->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function reorder(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer invoices');
    }
}
