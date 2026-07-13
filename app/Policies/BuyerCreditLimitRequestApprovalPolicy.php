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

    public function create(): bool
    {
        // Read-only resource - approvals are created through the request approval process
        return false;
    }

    public function update(): bool
    {
        // Read-only resource
        return false;
    }

    public function delete(): bool
    {
        // Read-only resource
        return false;
    }

    public function deleteAny(): bool
    {
        // Read-only resource
        return false;
    }

    public function restore(): bool
    {
        // Read-only resource
        return false;
    }

    public function restoreAny(): bool
    {
        // Read-only resource
        return false;
    }

    public function forceDelete(): bool
    {
        // Read-only resource - only admins could potentially force delete, but keeping false for consistency
        return false;
    }

    public function forceDeleteAny(): bool
    {
        // Read-only resource
        return false;
    }
}
