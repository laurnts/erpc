<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierOrderItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class SupplierOrderItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view supplier orders');
    }

    public function view(User $user, SupplierOrderItem $item): bool
    {
        return $user->belongsToTeam($item->supplierOrder->team)
            && $user->hasPermissionTo('view supplier orders');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier orders');
    }

    public function update(User $user, SupplierOrderItem $item): bool
    {
        return $user->belongsToTeam($item->supplierOrder->team)
            && $user->hasPermissionTo('update supplier orders');
    }

    public function delete(User $user, SupplierOrderItem $item): bool
    {
        return $user->belongsToTeam($item->supplierOrder->team)
            && $user->hasPermissionTo('update supplier orders');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier orders');
    }

    public function restore(User $user, SupplierOrderItem $item): bool
    {
        return $user->belongsToTeam($item->supplierOrder->team)
            && $user->hasPermissionTo('update supplier orders');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier orders');
    }

    public function forceDelete(User $user, SupplierOrderItem $item): bool
    {
        return $user->belongsToTeam($item->supplierOrder->team)
            && $user->hasPermissionTo('update supplier orders');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier orders');
    }

    public function reorder(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier orders');
    }
}
