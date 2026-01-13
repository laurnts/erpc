<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BuyerInvoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerInvoicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view buyer invoices');
    }

    public function view(User $user, BuyerInvoice $invoice): bool
    {
        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('view buyer invoices');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create buyer invoices');
    }

    public function update(User $user, BuyerInvoice $invoice): bool
    {
        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function delete(User $user, BuyerInvoice $invoice): bool
    {
        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('delete buyer invoices');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer invoices');
    }

    public function restore(User $user, BuyerInvoice $invoice): bool
    {
        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function forceDelete(User $user, BuyerInvoice $invoice): bool
    {
        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('delete buyer invoices');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer invoices');
    }
}
