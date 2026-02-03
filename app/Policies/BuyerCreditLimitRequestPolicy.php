<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BuyerCreditLimitRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerCreditLimitRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null;
    }

    public function view(User $user, BuyerCreditLimitRequest $buyerCreditLimitRequest): bool
    {
        return $user->belongsToTeam($buyerCreditLimitRequest->team);
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null;
    }

    public function update(User $user, BuyerCreditLimitRequest $buyerCreditLimitRequest): bool
    {
        return $user->belongsToTeam($buyerCreditLimitRequest->team);
    }

    public function delete(User $user, BuyerCreditLimitRequest $buyerCreditLimitRequest): bool
    {
        return $user->belongsToTeam($buyerCreditLimitRequest->team);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null;
    }

    public function restore(User $user, BuyerCreditLimitRequest $buyerCreditLimitRequest): bool
    {
        return $user->belongsToTeam($buyerCreditLimitRequest->team);
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null;
    }

    public function forceDelete(User $user, BuyerCreditLimitRequest $buyerCreditLimitRequest): bool
    {
        return $user->belongsToTeam($buyerCreditLimitRequest->team);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null;
    }
}
