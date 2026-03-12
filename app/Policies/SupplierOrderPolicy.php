<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CentralPurchasingRole;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Services\TeamMemberService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class SupplierOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Check if user is an administrator for the current team.
     */
    private function isAdmin(User $user): bool
    {
        $team = Filament::getTenant() ?? $user->currentTeam;
        return $team !== null && $user->hasTeamRole($team, 'admin');
    }

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->currentTeam !== null;
    }

    public function view(User $user, SupplierOrder $supplierOrder): bool
    {
        if (! $user->belongsToTeam($supplierOrder->team)) {
            return false;
        }

        // Administrators can view all supplier orders
        if ($this->isAdmin($user)) {
            return true;
        }

        // Check if user has permission to view supplier orders
        if ($user->hasPermissionTo('view supplier orders')) {
            return true;
        }

        // Also allow users with approval roles to view supplier orders for approval purposes
        /** @var \App\Models\Team|null $team */
        $team = Filament::getTenant() ?? $supplierOrder->team ?? $user->currentTeam;

        if ($team === null) {
            return false;
        }

        $approvalRoles = [
            CentralPurchasingRole::DEPT_HEAD_SALES,
            CentralPurchasingRole::DEPUTY_DIRECTOR,
            CentralPurchasingRole::DIRECTOR,
        ];

        foreach ($approvalRoles as $role) {
            $members = TeamMemberService::getTeamMembersByCentralPurchasingRole($team, $role);
            if ($members->contains('id', $user->id)) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create supplier orders');
    }

    public function update(User $user, SupplierOrder $supplierOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierOrder->team) && $supplierOrder->is_editable;
        }

        return $user->belongsToTeam($supplierOrder->team)
            && $user->hasPermissionTo('update supplier orders')
            && $supplierOrder->is_editable;
    }

    public function delete(User $user, SupplierOrder $supplierOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierOrder->team) && $supplierOrder->is_editable;
        }

        return $user->belongsToTeam($supplierOrder->team)
            && $user->hasPermissionTo('delete supplier orders')
            && $supplierOrder->is_editable;
    }

    public function deleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier orders');
    }

    public function restore(User $user, SupplierOrder $supplierOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierOrder->team);
        }

        return $user->belongsToTeam($supplierOrder->team)
            && $user->hasPermissionTo('update supplier orders');
    }

    public function restoreAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update supplier orders');
    }

    public function forceDelete(User $user, SupplierOrder $supplierOrder): bool
    {
        if ($this->isAdmin($user)) {
            return $user->belongsToTeam($supplierOrder->team);
        }

        return $user->belongsToTeam($supplierOrder->team)
            && $user->hasPermissionTo('delete supplier orders');
    }

    public function forceDeleteAny(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return $user->hasVerifiedEmail() && $user->currentTeam !== null;
        }

        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete supplier orders');
    }
}
