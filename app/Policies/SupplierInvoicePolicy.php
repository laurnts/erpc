<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class SupplierInvoicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view supplier invoices');
    }

    public function view(User $user, SupplierInvoice $invoice): bool
    {
        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('view supplier invoices');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create supplier invoices');
    }

    public function update(User $user, SupplierInvoice $invoice): bool
    {
        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('update supplier invoices');
    }

    public function delete(User $user, SupplierInvoice $invoice): bool
    {
        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('delete supplier invoices');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier invoices');
    }

    public function restore(User $user, SupplierInvoice $invoice): bool
    {
        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('update supplier invoices');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier invoices');
    }

    public function forceDelete(User $user, SupplierInvoice $invoice): bool
    {
        return $user->belongsToTeam($invoice->team)
            && $user->hasPermissionTo('delete supplier invoices');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier invoices');
    }
}
