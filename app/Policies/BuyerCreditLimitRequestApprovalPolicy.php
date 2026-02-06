<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BuyerCreditLimitRequestApproval;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerCreditLimitRequestApprovalPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null;
    }

    public function view(User $user, BuyerCreditLimitRequestApproval $buyerCreditLimitRequestApproval): bool
    {
        return $user->belongsToTeam($buyerCreditLimitRequestApproval->buyerCreditLimitRequest->team);
    }

    public function create(User $user): bool
    {
        // Read-only resource - approvals are created through the request approval process
        return false;
    }

    public function update(User $user, BuyerCreditLimitRequestApproval $buyerCreditLimitRequestApproval): bool
    {
        // Read-only resource
        return false;
    }

    public function delete(User $user, BuyerCreditLimitRequestApproval $buyerCreditLimitRequestApproval): bool
    {
        // Read-only resource
        return false;
    }

    public function deleteAny(User $user): bool
    {
        // Read-only resource
        return false;
    }

    public function restore(User $user, BuyerCreditLimitRequestApproval $buyerCreditLimitRequestApproval): bool
    {
        // Read-only resource
        return false;
    }

    public function restoreAny(User $user): bool
    {
        // Read-only resource
        return false;
    }

    public function forceDelete(User $user, BuyerCreditLimitRequestApproval $buyerCreditLimitRequestApproval): bool
    {
        // Read-only resource - only admins could potentially force delete, but keeping false for consistency
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        // Read-only resource
        return false;
    }
}
