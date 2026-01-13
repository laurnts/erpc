<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class SupplierOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view supplier orders');
    }

    public function view(User $user, SupplierOrder $supplierOrder): bool
    {
        return $user->belongsToTeam($supplierOrder->team)
            && $user->hasPermissionTo('view supplier orders');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create supplier orders');
    }

    public function update(User $user, SupplierOrder $supplierOrder): bool
    {
        return $user->belongsToTeam($supplierOrder->team)
            && $user->hasPermissionTo('update supplier orders')
            && $supplierOrder->is_editable;
    }

    public function delete(User $user, SupplierOrder $supplierOrder): bool
    {
        return $user->belongsToTeam($supplierOrder->team)
            && $user->hasPermissionTo('delete supplier orders')
            && $supplierOrder->is_editable;
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier orders');
    }

    public function restore(User $user, SupplierOrder $supplierOrder): bool
    {
        return $user->belongsToTeam($supplierOrder->team)
            && $user->hasPermissionTo('update supplier orders');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier orders');
    }

    public function forceDelete(User $user, SupplierOrder $supplierOrder): bool
    {
        return $user->belongsToTeam($supplierOrder->team)
            && $user->hasPermissionTo('delete supplier orders');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier orders');
    }
}
