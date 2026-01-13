<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BuyerOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view buyer orders');
    }

    public function view(User $user, BuyerOrder $buyerOrder): bool
    {
        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('view buyer orders');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create buyer orders');
    }

    public function update(User $user, BuyerOrder $buyerOrder): bool
    {
        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders')
            && $buyerOrder->status->canEdit();
    }

    public function delete(User $user, BuyerOrder $buyerOrder): bool
    {
        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('delete buyer orders')
            && $buyerOrder->status->canEdit();
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer orders');
    }

    public function restore(User $user, BuyerOrder $buyerOrder): bool
    {
        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer orders');
    }

    public function forceDelete(User $user, BuyerOrder $buyerOrder): bool
    {
        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('delete buyer orders');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer orders');
    }

    /**
     * Determine if the user can confirm the order.
     */
    public function confirm(User $user, BuyerOrder $buyerOrder): bool
    {
        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders')
            && $buyerOrder->status->canConfirm();
    }

    /**
     * Determine if the user can cancel the order.
     */
    public function cancel(User $user, BuyerOrder $buyerOrder): bool
    {
        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders')
            && $buyerOrder->status->canCancel();
    }

    /**
     * Determine if the user can progress the order status.
     */
    public function progressStatus(User $user, BuyerOrder $buyerOrder): bool
    {
        return $user->belongsToTeam($buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders')
            && $buyerOrder->status->canProgress();
    }
}
