<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class ShipmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view shipments');
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->belongsToTeam($shipment->team)
            && $user->hasPermissionTo('view shipments');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create shipments');
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->belongsToTeam($shipment->team)
            && $user->hasPermissionTo('update shipments');
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->belongsToTeam($shipment->team)
            && $user->hasPermissionTo('delete shipments');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete shipments');
    }

    public function restore(User $user, Shipment $shipment): bool
    {
        return $user->belongsToTeam($shipment->team)
            && $user->hasPermissionTo('update shipments');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update shipments');
    }

    public function forceDelete(User $user, Shipment $shipment): bool
    {
        return $user->belongsToTeam($shipment->team)
            && $user->hasPermissionTo('delete shipments');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete shipments');
    }
}
