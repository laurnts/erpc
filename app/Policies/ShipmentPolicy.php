<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ShipmentType;
use App\Models\Shipment;
use App\Models\User;
use App\Policies\Concerns\ResolvesPanelContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class ShipmentPolicy
{
    use HandlesAuthorization;
    use ResolvesPanelContext;

    private function userCanAccessPortalShipment(User $user, Shipment $shipment): bool
    {
        if ($shipment->type !== ShipmentType::OUTBOUND) {
            return false;
        }

        return $this->userOwnsBuyerCompany($user, $shipment->request?->buyer_id);
    }

    /**
     * Check if user is an administrator for the current team.
     */
    private function isAdmin(User $user): bool
    {
        $team = Filament::getTenant();

        return $team !== null && $user->hasTeamRole($team, 'admin');
    }

    public function viewAny(User $user): bool
    {
        if ($this->isCustomerPanel()) {
            return $user->hasActiveCustomerPortalAccess();
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view shipments');
    }

    public function view(User $user, Shipment $shipment): bool
    {
        if ($this->isCustomerPanel()) {
            return $this->userCanAccessPortalShipment($user, $shipment);
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($shipment->team);
        }

        return $user->belongsToTeam($shipment->team)
            && $user->hasPermissionTo('view shipments');
    }

    public function create(User $user): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create shipments');
    }

    public function update(User $user, Shipment $shipment): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($shipment->team);
        }

        return $user->belongsToTeam($shipment->team)
            && $user->hasPermissionTo('update shipments');
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($shipment->team);
        }

        return $user->belongsToTeam($shipment->team)
            && $user->hasPermissionTo('delete shipments');
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete shipments');
    }

    public function restore(User $user, Shipment $shipment): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($shipment->team);
        }

        return $user->belongsToTeam($shipment->team)
            && $user->hasPermissionTo('update shipments');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update shipments');
    }

    public function forceDelete(User $user, Shipment $shipment): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($shipment->team);
        }

        return $user->belongsToTeam($shipment->team)
            && $user->hasPermissionTo('delete shipments');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isCustomerPanel()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete shipments');
    }
}
