<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class SupplierPaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view supplier invoices');
    }

    public function view(User $user, SupplierPayment $payment): bool
    {
        return $user->belongsToTeam($payment->team)
            && $user->hasPermissionTo('view supplier invoices');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier invoices');
    }

    public function update(User $user, SupplierPayment $payment): bool
    {
        return $user->belongsToTeam($payment->team)
            && $user->hasPermissionTo('update supplier invoices');
    }

    public function delete(User $user, SupplierPayment $payment): bool
    {
        return $user->belongsToTeam($payment->team)
            && $user->hasPermissionTo('update supplier invoices');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier invoices');
    }

    public function restore(User $user, SupplierPayment $payment): bool
    {
        return $user->belongsToTeam($payment->team)
            && $user->hasPermissionTo('update supplier invoices');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier invoices');
    }

    public function forceDelete(User $user, SupplierPayment $payment): bool
    {
        return $user->belongsToTeam($payment->team)
            && $user->hasPermissionTo('update supplier invoices');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier invoices');
    }
}
