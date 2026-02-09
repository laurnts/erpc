<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CentralPurchasingRole;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Services\TeamMemberService;
use Filament\Facades\Filament;

class SupplierOrderApprovalPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        /** @var \App\Models\Team|null $team */
        $team = Filament::getTenant();

        if ($team === null) {
            return false;
        }

        // Check if user has one of the approval roles
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

        // Also show to users with admin permissions
        if ($user->hasPermissionTo('view supplier orders')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SupplierOrder $supplierOrder): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false; // Approvals cannot be created directly
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SupplierOrder $supplierOrder): bool
    {
        return false; // Approvals are handled via actions, not updates
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SupplierOrder $supplierOrder): bool
    {
        return false; // Approvals cannot be deleted
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SupplierOrder $supplierOrder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SupplierOrder $supplierOrder): bool
    {
        return false;
    }
}
