<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BuyerPayment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerPaymentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view buyer invoices');
    }

    public function view(User $user, BuyerPayment $payment): bool
    {
        return $user->belongsToTeam($payment->team)
            && $user->hasPermissionTo('view buyer invoices');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function update(User $user, BuyerPayment $payment): bool
    {
        return $user->belongsToTeam($payment->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function delete(User $user, BuyerPayment $payment): bool
    {
        return $user->belongsToTeam($payment->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function restore(User $user, BuyerPayment $payment): bool
    {
        return $user->belongsToTeam($payment->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function forceDelete(User $user, BuyerPayment $payment): bool
    {
        return $user->belongsToTeam($payment->team)
            && $user->hasPermissionTo('update buyer invoices');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer invoices');
    }
}
