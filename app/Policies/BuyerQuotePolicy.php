<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BuyerQuote;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class BuyerQuotePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('view buyer quotes');
    }

    public function view(User $user, BuyerQuote $buyerQuote): bool
    {
        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('view buyer quotes');
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('create buyer quotes');
    }

    public function update(User $user, BuyerQuote $buyerQuote): bool
    {
        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function delete(User $user, BuyerQuote $buyerQuote): bool
    {
        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('delete buyer quotes');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer quotes');
    }

    public function restore(User $user, BuyerQuote $buyerQuote): bool
    {
        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('update buyer quotes');
    }

    public function forceDelete(User $user, BuyerQuote $buyerQuote): bool
    {
        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('delete buyer quotes');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasVerifiedEmail()
            && $user->currentTeam !== null
            && $user->hasPermissionTo('delete buyer quotes');
    }

    /**
     * Determine if the user can send the quote.
     */
    public function send(User $user, BuyerQuote $buyerQuote): bool
    {
        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes')
            && $buyerQuote->status->canSend();
    }

    /**
     * Determine if the user can create a new version.
     */
    public function createVersion(User $user, BuyerQuote $buyerQuote): bool
    {
        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('create buyer quotes');
    }

    /**
     * Determine if the user can extend the validity.
     */
    public function extendValidity(User $user, BuyerQuote $buyerQuote): bool
    {
        return $user->belongsToTeam($buyerQuote->team)
            && $user->hasPermissionTo('update buyer quotes')
            && $buyerQuote->status->isActive();
    }
}
