<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BuyerOrderItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerOrderItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view buyer orders');
    }

    public function view(User $user, BuyerOrderItem $item): bool
    {
        return $user->belongsToTeam($item->buyerOrder->team)
            && $user->hasPermissionTo('view buyer orders');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer orders');
    }

    public function update(User $user, BuyerOrderItem $item): bool
    {
        return $user->belongsToTeam($item->buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders');
    }

    public function delete(User $user, BuyerOrderItem $item): bool
    {
        return $user->belongsToTeam($item->buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer orders');
    }

    public function restore(User $user, BuyerOrderItem $item): bool
    {
        return $user->belongsToTeam($item->buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer orders');
    }

    public function forceDelete(User $user, BuyerOrderItem $item): bool
    {
        return $user->belongsToTeam($item->buyerOrder->team)
            && $user->hasPermissionTo('update buyer orders');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer orders');
    }

    public function reorder(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer orders');
    }
}
