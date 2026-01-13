<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ShipmentItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class ShipmentItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view shipments');
    }

    public function view(User $user, ShipmentItem $item): bool
    {
        return $user->belongsToTeam($item->shipment->team)
            && $user->hasPermissionTo('view shipments');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update shipments');
    }

    public function update(User $user, ShipmentItem $item): bool
    {
        return $user->belongsToTeam($item->shipment->team)
            && $user->hasPermissionTo('update shipments');
    }

    public function delete(User $user, ShipmentItem $item): bool
    {
        return $user->belongsToTeam($item->shipment->team)
            && $user->hasPermissionTo('update shipments');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update shipments');
    }

    public function restore(User $user, ShipmentItem $item): bool
    {
        return $user->belongsToTeam($item->shipment->team)
            && $user->hasPermissionTo('update shipments');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update shipments');
    }

    public function forceDelete(User $user, ShipmentItem $item): bool
    {
        return $user->belongsToTeam($item->shipment->team)
            && $user->hasPermissionTo('update shipments');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update shipments');
    }

    public function reorder(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update shipments');
    }
}
